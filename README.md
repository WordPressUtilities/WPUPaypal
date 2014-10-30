# WPUPaypal

This plugin helps you to make paiements via PayPal

## How to make a paiement

```php
$WPUPaypal = new WPUPaypal();
$total = 10;
```

### New paiement

```php
if(!$WPUPaypal->isPaypalCallback()){
    // Redirection to PayPal
    $WPUPaypal->SetExpressCheckout(array(
        'successurl' => 'http://darklg.me/success' ,
        'returnurl' => 'http://darklg.me/return' ,
        'total' => $total,
        'name' => 'Order name',
        'desc' => 'Order description',
    ));
}
```

### Success

```php
if($WPUPaypal->isPaypalCallback()){
    // Back from PayPal
    $transactionId = $WPUPaypal->GetExpressCheckoutDetails(array(
        'total' => $total
    ));
    if ($transactionId != null) {
        // Transaction is complete !
    }
}
```
