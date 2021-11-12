<?php

namespace Imran\Xero;

use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use XeroAPI\XeroPHP\AccountingObjectSerializer;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Api\IdentityApi;
use XeroAPI\XeroPHP\ApiException;
use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Models\Accounting\Account;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\CreditNote;
use XeroAPI\XeroPHP\Models\Accounting\CreditNotes;
use XeroAPI\XeroPHP\Models\Accounting\Invoice;
use XeroAPI\XeroPHP\Models\Accounting\Invoices;
use XeroAPI\XeroPHP\Models\Accounting\Payment;

abstract class Xero
{
    const AUTHORIZE_URL = 'https://login.xero.com/identity/connect/authorize';
    const ACCESS_TOKEN_URL = 'https://identity.xero.com/connect/token';
    const RESOURCE_OWNER_DETAILS_URL = 'https://api.xero.com/api.xro/2.0/Organisation';

    /**
     * @var string
     */
    protected $clientID;
    /**
     * @var string
     */
    protected $clientSecret;
    /**
     * @var string
     */
    protected $redirectURI;
    /**
     *  Scopes that being sent with api
     * @var string[]
     */
    protected $scopes = [
        'openid',
        'email',
        'profile',
        'offline_access',
        'accounting.transactions',
        'accounting.transactions.read',
        'accounting.contacts',
        'accounting.contacts.read',
        'accounting.settings',
        'accounting.settings.read'
    ];
    /*
     * Store api provider instance
     */
    /**
     * @var GenericProvider
     */
    protected $provider;

    /**
     * @param string $clientID
     * @param string $clientSecret
     * @param string $redirectURI
     * @throws \Exception
     */
    public function __construct(
        string $clientID = '',
        string $clientSecret = '',
        string $redirectURI = ''
    )
    {
        if (empty($clientID)) {
            throw new \Exception('Client ID must be provided');
        }

        if (empty($clientSecret)) {
            throw new \Exception('Client Secret must be provided');
        }

        if (empty($redirectURI)) {
            throw new \Exception('Callback URL is missing');
        }

        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
        $this->redirectURI = $redirectURI;

        $provider = new GenericProvider([
            'clientId' => $this->clientID,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectURI,
            'urlAuthorize' => self::AUTHORIZE_URL,
            'urlAccessToken' => self::ACCESS_TOKEN_URL,
            'urlResourceOwnerDetails' => self::RESOURCE_OWNER_DETAILS_URL,
        ]);
        $this->provider = $provider;
    }

    /**
     * @return mixed
     */
    abstract public function test();

    /**
     * Set scopes and get Authorize URL to redirect API
     * @throws \Exception
     */
    public function getAuthorizeURL(): array
    {
        try {
            // Scope defines the data your app has permission to access.
            // Learn more about scopes at https://developer.xero.com/documentation/oauth2/scopes
            $options = [
                'scope' => implode(' ', $this->getScopes()),
            ];
            // This returns the authorizeUrl with necessary parameters applied (e.g. state).
            $authorizationUrl = $this->provider->getAuthorizationUrl($options);
            // Save the state generated for you and store it to the session.
            // For security, on callback we compare the saved state with the one returned to ensure they match.
            return [
                'url' => $authorizationUrl,
                'state' => $this->provider->getState(),
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $scopes
     * @return $this
     */
    public function setScopes(array $scopes): Xero
    {
        if (!empty($scopes) && count($scopes) > 0) {
            $this->scopes = array_merge($this->scopes, $scopes);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param string $code
     * @throws ApiException
     */
    public function getAccessToken(string $code)
    {
        if (empty($code)) {
            throw new \Exception('Please provide a authorization code');
        }

        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);
            $config = Configuration::getDefaultConfiguration()->setAccessToken((string)$accessToken->getToken());
            $identityApi = new IdentityApi(
                new Client(),
                $config,
            );
            $result = $identityApi->getConnections();
            $tenant_id = isset($result[0]) ? $result[0]->getTenantId() : '';
            $data = $this->prepareDataToStore($accessToken, $tenant_id);
            FileStorage::write($data);

        } catch (IdentityProviderException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $contactDetail
     * @param bool $returnObj
     * @return string|Contacts|\XeroAPI\XeroPHP\Models\Accounting\Error|null
     * @throws ApiException
     */
    public function createContact(array $contactDetail, bool $returnObj = false)
    {
        $this->refreshToken();
        $api = $this->getAccountingApiInstance();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $contact = new Contact($contactDetail);

        $contacts = new Contacts();
        $arr_contacts = [];
        array_push($arr_contacts, $contact);
        $contacts->setContacts($arr_contacts);
        $result = $api->createContacts($xeroTenantId, $contacts);
        return $returnObj ? $result : $result->getContacts()[0]->getContactId();
    }

    /**
     * @param string $email
     * @return false|string|null
     * @throws \Exception
     */
    public function getContact(string $email)
    {
        $this->refreshToken();
        $api = $this->getAccountingApiInstance();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $ifModifiedSince = null;
        $where = 'EmailAddress=="' . $email . '"';
        try {
            $result = $api->getContacts($xeroTenantId, $ifModifiedSince, $where);
            if (count($result) > 0) {
                return $result->getContacts()[0]->getContactId();
            }
            return false;
        } catch (\Exception $e) {
            throw new \Exception('Exception when calling AccountingApi->getContacts: ' . $e->getMessage(), PHP_EOL);
        }
    }

    /**
     * @throws \XeroAPI\XeroPHP\ApiException
     */
    public function createInvoice(array $invoiceData, bool $returnObj = false)
    {
        if (empty($invoiceData)) {
            throw new \Exception('Invoice data is required');
        }
        $this->refreshToken();
        $arr_invoices = [];
        $invoice = new Invoice($invoiceData);
        array_push($arr_invoices, $invoice);
        $invoices = new Invoices();
        $invoices->setInvoices($arr_invoices);
        $api = $this->getAccountingApiInstance();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $result = $api->createInvoices($xeroTenantId, $invoices);

        return $returnObj ? $result : $result->getInvoices()[0]->getInvoiceId();
    }

    /**
     * @param $invoiceID
     * @param $account_code
     * @param $amount
     * @param $date
     * @return string|null
     * @throws \Exception
     */
    public function createPayment($invoiceID, $account_code, $amount, $date)
    {
        if (empty($invoiceID) || empty($amount) || empty($date)) {
            throw new \Exception('Provide all required data');
        }

        $this->refreshToken();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $api = $this->getAccountingApiInstance();
        $allAccounts = $this->getBankAccount($api, $xeroTenantId, $account_code);
        $accountId = $allAccounts->getAccounts()[0]->getAccountId();

        $invoice = new Invoice();
        $invoice->setInvoiceId($invoiceID);

        $account = new Account();
        $account->setAccountId($accountId);

        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAccount($account);
        $payment->setAmount($amount);
        $payment->setDate($date);
        $payment->setStatus('AUTHORISED');
//        return $payment;
//        $payments = new Payments();
//        $arr_payments = [];
//        array_push($arr_payments, $payment);
//        $payments->setPayments($arr_payments);
//        return $payment;
        try {
            $result = $api->createPayment($xeroTenantId, $payment);
            return $result->getPayments()[0]->getPaymentID();
        } catch (ApiException $exception) {
            $error = AccountingObjectSerializer::deserialize(
                $exception->getResponseBody(),
                '\XeroAPI\XeroPHP\Models\Accounting\Error',
            );
            throw new \Exception('Exception when calling AccountingApi->createPayment: ' . $error['error_number'] . ' => ' . $error['message'] . PHP_EOL);
        }

    }

    /**
     * @param $paymentData
     * @return string|null
     * @throws \Exception
     */
    public function createCreditNotePayment($paymentData)
    {
        if (empty($paymentData)) {
            throw new \Exception('Provide all required data');
        }

        $this->refreshToken();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $api = $this->getAccountingApiInstance();
        $allAccounts = $this->getBankAccount($api, $xeroTenantId, !empty($paymentData) ? $paymentData['code'] : null);
        $accountId = $allAccounts->getAccounts()[0]->getAccountId();

        $account = new Account();
        $account->setAccountId($accountId);

        $payment = new Payment($paymentData);
        $payment->setAccount($account);

        try {
            $result = $api->createPayment($xeroTenantId, $payment);
            return $result->getPayments()[0]->getPaymentID();
        } catch (ApiException $exception) {
            $error = AccountingObjectSerializer::deserialize(
                $exception->getResponseBody(),
                '\XeroAPI\XeroPHP\Models\Accounting\Error',
            );
            throw new \Exception('Exception when calling credit note payment AccountingApi->createPayment: ' . $error['error_number'] . ' => ' . $error['message'] . PHP_EOL);
        }

    }

    /**
     * @param array $creditNotesData
     * @param bool $returnObj
     * @return string|CreditNotes|\XeroAPI\XeroPHP\Models\Accounting\Error|null
     * @throws \Exception
     */
    public function createCreditNotes(array $creditNotesData, bool $returnObj = false)
    {
        if (empty($creditNotesData)) {
            throw new \Exception('Please provide credit notes data to be used');
        }
        $this->refreshToken();
        $xeroTenantId = FileStorage::getXeroTenantId();
        $creditNote = new CreditNote($creditNotesData);

        $creditNotes = new CreditNotes();
        $arr_credit_notes = [];
        array_push($arr_credit_notes, $creditNote);
        $creditNotes->setCreditNotes($arr_credit_notes);

        $api = $this->getAccountingApiInstance();
        try {
            $result = $api->createCreditNotes($xeroTenantId, $creditNotes);
            return $returnObj ? $result : $result->getCreditNotes()[0]->getCreditNoteId();
        } catch (ApiException $exception) {
            $error = AccountingObjectSerializer::deserialize(
                $exception->getResponseBody(),
                '\XeroAPI\XeroPHP\Models\Accounting\Error',
            );
            throw new \Exception('Exception when calling AccountingApi->createCreditNotes: ' . $error['error_number'] . ' => ' . $error['message'] . PHP_EOL);
        }
    }

    /**
     * @param $apiInstance
     * @param $xeroTenantId
     * @param null $code
     * @return mixed
     */
    public function getBankAccount($apiInstance, $xeroTenantId, $code = null)
    {
        $where = 'Status=="ACTIVE"';
        if (!empty($code)) {
            $where .= 'AND Code=="' . $code . '"';
        }
        return $apiInstance->getAccounts($xeroTenantId, null, $where);
    }

    /**
     * @return AccountingApi
     */
    private function getAccountingApiInstance(): AccountingApi
    {
        $config = Configuration::getDefaultConfiguration()->setAccessToken((string)FileStorage::getAccessToken());
        return new AccountingApi(new Client(), $config);
    }

    /**
     * @throws IdentityProviderException
     */
    public function refreshToken()
    {
        if (FileStorage::hasExpired()) {
            $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => FileStorage::getRefreshToken()
            ]);

            $tenant_id = (string)FileStorage::getXeroTenantId();
            $data = $this->prepareDataToStore($newAccessToken, $tenant_id);
            FileStorage::write($data);
        }
    }

    /**
     * @param $accessToken
     * @param $tenantID
     * @return array
     */
    protected function prepareDataToStore($accessToken, $tenantID): array
    {
        return [
            'token' => $accessToken->getToken(),
            'expires' => $accessToken->getExpires(),
            'tenant_id' => $tenantID,
            'refresh_token' => $accessToken->getRefreshToken(),
            'id_token' => $accessToken->getValues()['id_token'],
        ];
    }
}
