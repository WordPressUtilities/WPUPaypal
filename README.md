# WPUPaypal

This WordPress plugin helps you make payments via PayPal

## Account

### Test

* Create a test [business account](https://developer.paypal.com/webapps/developer/applications/accounts).
* Get User / PWD / Sig from this test account.
* Set-up the plugin in "sandbox" mode with these credentials.
* Set API version to 96.0.

### Production

* Upgrade your PayPal account to a business account.
* Get your [API signature](https://www.paypal.com/fr/cgi-bin/webscr?cmd=_profile-api-access).
* Set-up the plugin in "live" mode with these credentials.

## Code

### How to make a paiement

```php
$WPUPaypal = new WPUPaypal();
$total = 10;
```

#### New paiement

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

#### Success

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
