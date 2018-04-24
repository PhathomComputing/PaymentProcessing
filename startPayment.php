<?php
/*
	* Contains call to create payment object and receive Approval URL to which user is then redirected to.
*/
if ( session_id() == "" ) {
	session_start();
}
require_once( 'utilFunctions.php' );
require_once( 'paypalFunctions.php' );
require_once( '../core/init.php' );
$access_token             = getAccessToken();
$_SESSION['access_token'] = $access_token;
if ( verify_nonce() ) {
	$array_items = [ 'items' => [] ];
	if ( $cart_id != '' ) {
		$pro = [];
		$q   = $db->query( "SELECT * FROM `products`" );
		while( $dato = $q->fetch_assoc() ){
			$pro[ $dato["id"] ]["title"]       = $dato["title"];
			$pro[ $dato["id"] ]["description"] = $dato["description"];
		}
		$cartQ  = $db->query( "SELECT * FROM cart WHERE id = '" . $_POST["cart_id"] . "'" );
		$result = mysqli_fetch_assoc( $cartQ );
		$items  = json_decode( $result['items'], true );
		$i      = 0;
		$coma   = '';
		foreach ( $items as $key => $value ) {
			$tax = 0.065 * $pro[ $value["id"] ]["price"];
			$tax = number_format( $tax, 2 );

			$description = str_replace( array( "\r", "\n", "\t" ), ' ', $pro[ $value["id"] ]["description"] );
			$description = strip_tags( $description );
			$description .= ' ';

			$product_info = [
				"name"        => $value['name'],
				"quantity"    => $value['quantity'],
				"price"       => $value['unit_price'],
				"sku"         => $value['sku'],
				"description" => $description,
				"tax"         => "0.00",
				"currency"    => "USD"
			];
			array_push( $array_items['items'], $product_info );
			$i ++;
		}
	}

	$item_list = json_encode( $array_items );

	$hostName      = $_SERVER['HTTP_HOST'];
	$appName       = explode( "/", $_SERVER['REQUEST_URI'] )[1];
	$cancelUrl     = "http://" . $hostName . "/" . $appName . "/cancel.php";
	$payUrl        = "http://" . $hostName . "/" . $appName . "/pay.php";
	$placeOrderUrl = "http://" . $hostName . "/" . $appName . "/placeOrder.php";

	$expressCheckoutArray = [
		'transactions'  => [
			[
				'amount'          => [
					'currency' => $_POST['currencyCodeType'],
					'total'    => number_format( (float) $_POST['camera_amount'] + (float) $_POST['estimated_shipping'] + (float) $_POST['tax'] + (float) $_POST['insurance'] + (float) $_POST['handling_fee'] + (float) $_POST['shipping_discount'], 2, '.', ',' ),
					'details'  => [
						'shipping'          => number_format( $_POST['estimated_shipping'], 2, '.', ',' ),
						"subtotal"          => number_format( $_POST['camera_amount'], 2, '.', ',' ),
						"tax"               => number_format( $_POST['tax'], 2, '.', ',' ),
						"insurance"         => number_format( $_POST['insurance'], 2, '.', ',' ),
						"handling_fee"      => number_format( $_POST['handling_fee'], 2, '.', ',' ),
						"shipping_discount" => number_format( $_POST['shipping_discount'], 2, '.', ',' ),
					]
					,
				],
				'description'     => $_POST['description'],
				'item_list'       => $array_items,
				'payment_options' => [
					"allowed_payment_method" => "INSTANT_FUNDING_SOURCE",
				],
			],
		],
		'payer'         => [
			"payment_method"                            => "paypal",
		],
		"intent"        => "sale",
		"redirect_urls" => [
			"cancel_url" => $cancelUrl,
			"return_url" => $placeOrderUrl,
		],
	];

	$_SESSION['expressCheckoutPaymentData'] = json_encode( $expressCheckoutArray );
	$approval_url                           = getApprovalURL( $access_token, $_SESSION['expressCheckoutPaymentData'] );
	$dautl                                  = explode( "=", $approval_url );
	$tran                                   = $db->query( "SELECT * FROM `transactions` WHERE `cart_id` = '" . $_POST["cart_id"] . "'" );
	$dato                                   = $tran->fetch_assoc();
	if ( $dato["id"] == "" ) {
		$db->query( "INSERT INTO `transactions` (`cart_id`, `sub_total`, `tax`, `grand_total`, `description`, `token`) 
													VALUES ('" . $_POST["cart_id"] . "', '" . $_POST['camera_amount'] . "', '" . $_POST['tax'] . "', '" . $ttoal . "', '','" . $dautl[2] . "')" );
	} else {
		$sql = "UPDATE `transactions` SET `token`='" . $dautl[2] . "', `sub_total` = '" . $_POST['camera_amount'] . "', `tax` = '" . $_POST['tax'] . "',  `grand_total` = '" . $ttoal . "' WHERE (`id`='" . $dato["id"] . "')";
		$db->query( $sql );
	}
	//header( "location:$approval_url" );
	echo '<meta http-equiv="refresh" content="0; url='.$approval_url.'">';

} else {
	die( 'Session expired' );
}
