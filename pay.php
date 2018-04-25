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
	* Payment Confirmation page : has call to execute the payment and displays the Confirmation details
*/
if ( session_id() == "" ) {
	session_start();
}

include( 'utilFunctions.php' );
include( 'paypalFunctions.php' );

if ( isset( $_GET['paymentId'] ) && isset( $_GET['PayerID'] ) ) { //Proceed to Checkout or Mark flow
	$response = doPayment( filter_input( INPUT_GET, 'paymentId', FILTER_SANITIZE_STRING ), filter_input( INPUT_GET, 'PayerID', FILTER_SANITIZE_STRING ), null );

} else { //Express checkout flow
	if ( verify_nonce() ) {
		$expressCheckoutFlowArray                                                     = json_decode( $_SESSION['expressCheckoutPaymentData'], true );
		$expressCheckoutFlowArray['transactions'][0]['amount']['total']               = (float) $expressCheckoutFlowArray['transactions'][0]['amount']['total'] + (float) $_POST['shipping_method'] - (float) $expressCheckoutFlowArray['transactions'][0]['amount']['details']['shipping'];
		$expressCheckoutFlowArray['transactions'][0]['amount']['details']['shipping'] = $_POST['shipping_method'];
		$transactionAmountUpdateArray                                                 = $expressCheckoutFlowArray['transactions'][0];
		$_SESSION['expressCheckoutPaymentData']                                       = json_encode( $expressCheckoutFlowArray );
		$transactionAmountUpdateArray[item_list][items][0][description]='';
		$response = doPayment( $_SESSION['paymentID'], $_SESSION['payerID'], $transactionAmountUpdateArray );
	} else {
		die( 'Session expired' );
	}
}

if ( $response['http_code'] != 200 && $response['http_code'] != 201 ) {
	$_SESSION['error'] = $response;
	header( 'Location: error.php' );
	exit();
}

$json_response = $response['json'];
$paymentID     = $json_response['id'];
$paymentState  = $json_response['state'];
$finalAmount   = $json_response['transactions'][0]['amount']['total'];
$currency      = $json_response['transactions'][0]['amount']['currency'];
$transactionID = $json_response['transactions'][0]['related_resources'][0]['sale']['id'];

$payerFirstName = filter_var( $json_response['payer']['payer_info']['first_name'], FILTER_SANITIZE_SPECIAL_CHARS );
$payerLastName  = filter_var( $json_response['payer']['payer_info']['last_name'], FILTER_SANITIZE_SPECIAL_CHARS );
$recipientName  = filter_var( $json_response['payer']['payer_info']['shipping_address']['recipient_name'], FILTER_SANITIZE_SPECIAL_CHARS );
$addressLine1   = filter_var( $json_response['payer']['payer_info']['shipping_address']['line1'], FILTER_SANITIZE_SPECIAL_CHARS );
$addressLine2   = ( isset( $json_response['payer']['payer_info']['shipping_address']['line2'] ) ? filter_var( $json_response['payer']['payer_info']['shipping_address']['line2'], FILTER_SANITIZE_SPECIAL_CHARS ) : "" );
$city           = filter_var( $json_response['payer']['payer_info']['shipping_address']['city'], FILTER_SANITIZE_SPECIAL_CHARS );
$state          = filter_var( $json_response['payer']['payer_info']['shipping_address']['state'], FILTER_SANITIZE_SPECIAL_CHARS );
$postalCode     = filter_var( $json_response['payer']['payer_info']['shipping_address']['postal_code'], FILTER_SANITIZE_SPECIAL_CHARS );
$countryCode    = filter_var( $json_response['payer']['payer_info']['shipping_address']['country_code'], FILTER_SANITIZE_SPECIAL_CHARS );

require_once( '../core/init.php' );

$sql = "UPDATE `transactions` SET `paid`='1', `status` = '" . $paymentState . "' WHERE (`transaction-pay`='" . $paymentID . "')";
$db->query( $sql );
$qu  = $db->query( "SELECT * FROM `transactions` WHERE `transaction-pay` = '" . $paymentID . "' " );
$idc = $qu->fetch_assoc();

$ca   = $db->query( "SELECT * FROM  `cart` WHERE  `id` ='" . $idc["cart_id"] . "' " );
$daca = $ca->fetch_assoc();
$nn = json_decode( $daca['items'], true );
for ( $i = 0; $i < count( $nn ); $i ++ ) {
	if ( $i > 0 ) {
		$coma = ',';
	}
	$id_item .= $coma . $nn[ $i ]["id"] . "|" . $nn[ $i ]["quantity"];
}

$nca = $db->query( "INSERT INTO `cart_ship` (`id` ,`items` ,`expire_date` ,`paid` ,`shipped`) VALUES ( '" . $daca["id"] . "', '" . $id_item . "',  '" . $daca["expire_date"] . "',  '" . $daca["paid"] . "',  '" . $daca["shipped"] . "')" );

$domain = ( ( $_SERVER['HTTP_HOST'] != 'localhost' ) ? '.' . $_SERVER['HTTP_HOST'] : false );
$db->query( "DELETE FROM cart WHERE id = '" . $idc["cart_id"] . "'" );
setcookie( CART_COOKIE, '', 1, "/", $domain, false );

?>
<div class="wrap10 margint5 padding1">
  <div class="wrap3 col-wrap padding1 bwhite box-shadow center">
    <div class="wrap10">
      <label>
        <p style="text-align: center; font-size: 14px;  "><label> <?php echo( $payerFirstName . ' ' . $payerLastName . ', Thank you for your Order!' ); ?><p></label>
      Shipping Address:

      <p> Direction: <br><?php echo( $addressLine1 ); ?></p>
      <p>Direction 2: <?php echo( $addressLine2 ); ?></p>
      <p> City: <br><?php echo( $city ); ?></p>
      <p> State / Postal Code: <br> <?php echo( $state . '-' . $postalCode ); ?></p>
      <p> Country: <br><?php echo( $countryCode ); ?></p>

      <label>Payment ID: <?php echo( $paymentID ); ?> <br/>
        Transaction ID : <?php echo( $transactionID ); ?> <br/>
        State : <?php echo( $paymentState ); ?> <br/>
        Total Amount: <?php echo( $finalAmount ); ?> &nbsp; <?php echo( $currency ); ?> <br/>
      </label>
      <br/>
      Return to <a href="../index.php">home page</a>. 
    </div>
    <div class="col-md-4"></div>
  </div>
</div>
<?php

// send email
$para = $json_response['payer']['payer_info']['email']; // atención a la coma

$título = 'email@email.com Payment';

$items     = $json_response['transactions'][0]['item_list'];
$list_item = '<table width="100%" cellpadding="5" cellspacing="5" style="margin-bottom:20px;">';
$list_item .= '<tr>';
$list_item .= '<th>' . implode( '</th><th>', [ 'Qty', 'Description', 'Price', 'Total' ] ) . '</th>';
$list_item .= '</tr>';
foreach ( $items['items'] as $item ) {
	$sku = isset($item['sku']) ? $item['sku'] . ' / ' : '';
	$sku ='';
	$list_item .= '<tr>';
	$list_item .= "<td>{$item['quantity']}</td>";
	$list_item .= "<td>{$sku}{$item['name']} / {$item['description']}</td>";
	$list_item .= "<td>{$item['currency']}{$item['price']}</td>";
	$total     = $item['price'] * $item['quantity'];
	$list_item .= "<td>{$item['currency']}{$total}</td>";
	$list_item .= '</tr>';
}
$list_item .= '</table>';

$mensaje = '<html>
<head>
  <title>Payment</title>
</head>
<body>
  <p>' . $payerFirstName . ' ' . $payerLastName . ', Thank you for your Order!' . '</p>
  <table>
    <tr><td>' . $list_item . '<br></td></tr>
    <tr>
      <td>Payment ID <br>' . $paymentID . '</td>
      <td>Transaction ID <br>' . $transactionID . '</td>
      <td>State <br>' . ucwords( $paymentState ) . '</td>
    </tr>
    <tr>
      <td><strong>Shipping informaction:</strong><br>' .
           $recipientName . '<br> ' .
           $addressLine1 . ' ' .
           $addressLine2 . '<br>' .
           $city . '<br>' .
           $countryCode . '<br>' .
           $state . '-' . $postalCode . '</td>
    </tr>
    <tr>
      <td>Total Amount:' . $finalAmount . '</td>
    </tr>
  </table>
</body>
</html>';

$cabeceras = 'MIME-Version: 1.0' . "\r\n";
$cabeceras .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

$cabeceras .= 'To: <' . $json_response['payer']['payer_info']['email'] . "\r\n";
$cabeceras .= 'From: Paid <email@email.com>' . "\r\n";
$cabeceras .= 'Cc: email@email.com' . "\r\n";
mail( $para, $título, $mensaje, $cabeceras );

if ( session_id() !== "" ) {
	session_unset();
	session_destroy();
}
include( 'footer.php' ); ?>
</body>
</html>
