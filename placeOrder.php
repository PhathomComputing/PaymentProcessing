<?php session_start(); ?>
<html>
<head>
  <title>Ship To :</title>
  <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="css/bootstrap-responsive.min.css">
  <link rel="stylesheet" type="text/css" href="css/basic.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
</head>
<body class="blightgrey">
<?php
/*
	* Place Order Page : part of the Express Checkout flow. Buyer can choose shipping option on this page.
*/
if ( session_id() == "" ) {
	session_start();
}

require_once( 'utilFunctions.php' );
require_once( 'paypalFunctions.php' );
require_once( '../core/init.php' );

$_SESSION['paymentID'] = filter_input( INPUT_GET, 'paymentId', FILTER_SANITIZE_STRING );
$_SESSION['payerID']   = filter_input( INPUT_GET, 'PayerID', FILTER_SANITIZE_STRING );
$access_token          = $_SESSION['access_token'];
$lookUpPaymentInfo     = lookUpPaymentDetails( $_SESSION['paymentID'], $access_token );
$recipientName         = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['recipient_name'], FILTER_SANITIZE_SPECIAL_CHARS );
$addressLine1          = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['line1'], FILTER_SANITIZE_SPECIAL_CHARS );
$addressLine2          = ( isset( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['line2'] ) ? filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['line2'], FILTER_SANITIZE_SPECIAL_CHARS ) : "" );
$city                  = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['city'], FILTER_SANITIZE_SPECIAL_CHARS );
$state                 = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['state'], FILTER_SANITIZE_SPECIAL_CHARS );
$postalCode            = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['postal_code'], FILTER_SANITIZE_SPECIAL_CHARS );
$countryCode           = filter_var( $lookUpPaymentInfo['payer']['payer_info']['shipping_address']['country_code'], FILTER_SANITIZE_SPECIAL_CHARS );
$p_status = filter_var( $lookUpPaymentInfo['payer']['status'], FILTER_SANITIZE_SPECIAL_CHARS );
$p_email  = filter_var( $lookUpPaymentInfo['payer']['payer_info']['email'], FILTER_SANITIZE_SPECIAL_CHARS );
$url   = filter_var( $lookUpPaymentInfo['links'][2]['href'], FILTER_SANITIZE_SPECIAL_CHARS );
$dautl = explode( "=", $url );
$sql = "UPDATE `transactions` SET `email` = '" . $p_email . "',`full_name` = '" . $recipientName . "',`city` = '" . $city . "',`state` = '" . $state . "', `transaction-pay` = '" . $_SESSION['paymentID'] . "' WHERE (`token`='" . $dautl[2] . "')";
$db->query( $sql );

?>
<div class="wrap10 margint5 padding1">
  <div class="wrap3 center col-wrap bwhite box-shadow midle-radius">
    <div class="wrap10"></div>
    <div class="wrap10 padding1">
      <h3>Ship To :</h3>
      <p style="text-align: center; font-size: 18px"><label>  <?php echo( $recipientName ); ?> </label></p>
      <p> Direction: <br><?php echo( $addressLine1 ); ?></p>
      <p>Direction 2: <?php echo( $addressLine2 ); ?></p>
      <p> City: <br><?php echo( $city ); ?></p>
      <p> State / Postal Code: <br> <?php echo( $state . '-' . $postalCode ); ?></p>
      <p> Country: <br><?php echo( $countryCode ); ?></p>
      <form action="pay.php" method="POST">
        <input type="text" name="csrf" value="<?php echo( $_SESSION['csrf'] ); ?>" hidden readonly>
        <input type="text" name="paymentID" value="<?php echo( $_SESSION['paymentID'] ); ?>" hidden readonly>
        <input type="text" name="payerID" value="<?php echo( $_SESSION['payerID'] ); ?>" hidden readonly>
        <button type="submit" class="btn btn-primary center wrap5 last-full-size dpblock">Confirm Order</button>
      </form>

      <br/>
    </div>
    <div class="col-md-4"></div>
  </div>
</div>
<?php include( 'footer.php' ); ?>
</body>
</html>
