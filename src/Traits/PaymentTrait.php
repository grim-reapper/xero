<?php

namespace Imran\Xero\Traits;

trait PaymentTrait
{
    protected static $attributeMap = [
        'invoice' => 'Invoice',
        'credit_note' => 'CreditNote',
        'prepayment' => 'Prepayment',
        'overpayment' => 'Overpayment',
        'invoice_number' => 'InvoiceNumber',
        'credit_note_number' => 'CreditNoteNumber',
        'account' => 'Account',
        'code' => 'Code',
        'date' => 'Date',
        'currency_rate' => 'CurrencyRate',
        'amount' => 'Amount',
        'bank_amount' => 'BankAmount',
        'reference' => 'Reference',
        'is_reconciled' => 'IsReconciled',
        'status' => 'Status',
        'payment_type' => 'PaymentType',
        'updated_date_utc' => 'UpdatedDateUTC',
        'payment_id' => 'PaymentID',
        'batch_payment_id' => 'BatchPaymentID',
        'bank_account_number' => 'BankAccountNumber',
        'particulars' => 'Particulars',
        'details' => 'Details',
        'has_account' => 'HasAccount',
        'has_validation_errors' => 'HasValidationErrors',
        'status_attribute_string' => 'StatusAttributeString',
        'validation_errors' => 'ValidationErrors'
    ];

    protected static $setters = [
        'invoice' => 'setInvoice',
        'credit_note' => 'setCreditNote',
        'prepayment' => 'setPrepayment',
        'overpayment' => 'setOverpayment',
        'invoice_number' => 'setInvoiceNumber',
        'credit_note_number' => 'setCreditNoteNumber',
        'account' => 'setAccount',
        'code' => 'setCode',
        'date' => 'setDate',
        'currency_rate' => 'setCurrencyRate',
        'amount' => 'setAmount',
        'bank_amount' => 'setBankAmount',
        'reference' => 'setReference',
        'is_reconciled' => 'setIsReconciled',
        'status' => 'setStatus',
        'payment_type' => 'setPaymentType',
        'updated_date_utc' => 'setUpdatedDateUtc',
        'payment_id' => 'setPaymentId',
        'batch_payment_id' => 'setBatchPaymentId',
        'bank_account_number' => 'setBankAccountNumber',
        'particulars' => 'setParticulars',
        'details' => 'setDetails',
        'has_account' => 'setHasAccount',
        'has_validation_errors' => 'setHasValidationErrors',
        'status_attribute_string' => 'setStatusAttributeString',
        'validation_errors' => 'setValidationErrors'
    ];
}
