#Xero OAuth2.0 API Integration

> This package is just created to use in personal project, so all the function implemented according to our personal project. Please check this before use it.

```php
// To use this package you have to extend it, create your own PHP class and extend this.
class YourClassName extends \Imran\Xero\Xero{
    // then override it constructor
    public function __construct(array $params)
    {
        if(!empty($params)) {
            parent::__construct($params['client_id'], $params['client_secret'], $params['redirect_uri']);
        }
    }
   // that's it
}
```
Create your class object first
```php
    $object = new YourClassName();
```
To get Authorize URL
```php
    $data = $object->getAuthorizeURL();
    // it will return array containing URL and State Code
    $state = $data['state']; // this is used to verify state after api redirect to prevent xss
    $url = $data['url']; // use this url to redirect to Xero website for Authentication
```
To add scopes
```php
    $object->setScopes(['scope1','scope2']);
```
To get Access Token after redirection to your site
```php
    $code = $_GET['code']; // get code param from url and pass it to function
    $accessToken = $object->getAccessToken($code);
```
To create contact
```php
    $contact = $object->createContact(array $contactData);
```
To get a contact
```php
    $contact = $object->getContact(string $emailAddress);
```

To create an invoice
```php
    $invoice = $object->createInvoice(array $invoiceData);
```
To create a Payment
```php
    $payment = $object->createPayment($invoiceID, $account_code, $amount, $date);
```
To create a Credit Note
```php
    $creditNote = $object->createCreditNotes(array $creditNotesData, bool $returnObj = false);
```
To make Credit Note Payment
```php
    $creditNote = $object->createCreditNotePayment(array $paymentData);
```
