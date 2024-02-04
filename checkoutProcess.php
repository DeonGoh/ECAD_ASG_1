<?php
session_start();
include("header.php"); // Include the Page Layout header
include_once("myPayPal.php"); // Include the file that contains PayPal settings
include_once("mysql_conn.php"); 

if($_POST) //Post Data received from Shopping cart page.
{
	//=================================
	//
	//	Check if out of stock code
	//
	//=================================

	foreach($_SESSION['Items'] as $key=>$item) {
		$qry = "SELECT Quantity FROM Product WHERE ProductID = ? ";
		$stmt = $conn->prepare($qry);
		$stmt ->bind_param("i",$item['productId']); // "i" - integer
		$stmt ->execute();
		$result = $stmt->get_result();
		$stmt->close();
		while ($row = $result->fetch_array()) {
			if($row["Quantity"] < $item['quantity']){
				echo "Product $item[productId] : $item[name] is out of stock!<br/>";
				echo "Please return to shopping cart to amend your purchase.<br/>";
				include("footer.php");
				exit;
			}
		}
	}
	//================================
	//
	// Tax Rate retrieved from Table
	//
	//================================

	$qry = "SELECT EffectiveDate, TaxRate FROM gst ORDER BY EffectiveDate Desc";
	$stmt = $conn->prepare($qry);
	$stmt->execute();
	$result = $stmt->get_result();
	$stmt->close();
	// Display each product in a row
	while($row = $result->fetch_array()){
		if(DateTime::createFromFormat("Y-m-d", $row["EffectiveDate"]) <= new DateTime()){	
			$taxRate = $row["TaxRate"];
			break;
		}
	}

	$_SESSION["TaxRate"] = $taxRate;

	// Get GST from table in database and round the figure to 2 decimal places
	$_SESSION["Tax"] = round($_SESSION["SubTotal"] * ($taxRate/100), 2);

	// Compute Shipping charge 
	//	- S$5.00 for normal
	//	- S$10.00 for Express
	//	- S$0.00 for Express when subtotal > S$200

	$_SESSION["ShipCharge"] = $_SESSION["DeliveryCharge"];
	
	$paypal_data = '';
	// Get all items from the shopping cart, concatenate to the variable $paypal_data
	// $_SESSION['Items'] is an associative array
	foreach($_SESSION['Items'] as $key=>$item) {
		$paypal_data .= '&L_PAYMENTREQUEST_0_QTY'.$key.'='.urlencode($item["quantity"]);
	  	$paypal_data .= '&L_PAYMENTREQUEST_0_AMT'.$key.'='.urlencode($item["price"]);
	  	$paypal_data .= '&L_PAYMENTREQUEST_0_NAME'.$key.'='.urlencode($item["name"]);
		$paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER'.$key.'='.urlencode($item["productId"]);
	}
	
	//Data to be sent to PayPal
	$padata = '&CURRENCYCODE='.urlencode($PayPalCurrencyCode).
			  '&PAYMENTACTION=Sale'.
			  '&ALLOWNOTE=1'.
			  '&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($PayPalCurrencyCode).
			  '&PAYMENTREQUEST_0_AMT='.urlencode($_SESSION["SubTotal"] +
				                                 $_SESSION["Tax"] + 
												 $_SESSION["ShipCharge"]).
			  '&PAYMENTREQUEST_0_ITEMAMT='.urlencode($_SESSION["SubTotal"]). 
			  '&PAYMENTREQUEST_0_SHIPPINGAMT='.urlencode($_SESSION["ShipCharge"]). 
			  '&PAYMENTREQUEST_0_TAXAMT='.urlencode($_SESSION["Tax"]). 	
			  //=============================
			  //
			  // IMPORTANT!!!
			  // ENTER OUR WEBSITE NAME HERE v
			  //
			  //=============================
			  '&BRANDNAME='.urlencode("PetalCraft"). 
			  $paypal_data.				
			  '&RETURNURL='.urlencode($PayPalReturnURL).
			  '&CANCELURL='.urlencode($PayPalCancelURL);	
		
	//We need to execute the "SetExpressCheckOut" method to obtain paypal token
	$httpParsedResponseAr = PPHttpPost('SetExpressCheckout', $padata, $PayPalApiUsername, 
	                                   $PayPalApiPassword, $PayPalApiSignature, $PayPalMode);
		
	//Respond according to message we receive from Paypal
	if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || 
	   "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {					
		if($PayPalMode=='sandbox')
			$paypalmode = '.sandbox';
		else
			$paypalmode = '';
				
		//Redirect user to PayPal store with Token received.
		$paypalurl ='https://www'.$paypalmode. 
		            '.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token='.
					$httpParsedResponseAr["TOKEN"].'';
		header('Location: '.$paypalurl);
	}
	else {
		//Show error message
		echo "<div style='color:red'><b>SetExpressCheckOut failed : </b>".
		      urldecode($httpParsedResponseAr["L_LONGMESSAGE0"])."</div>";
		echo "<pre>".print_r($httpParsedResponseAr)."</pre>";
	}
}

//Paypal redirects back to this page using ReturnURL, We should receive TOKEN and Payer ID
if(isset($_GET["token"]) && isset($_GET["PayerID"])) 
{	
	//we will be using these two variables to execute the "DoExpressCheckoutPayment"
	//Note: we haven't received any payment yet.
	$token = $_GET["token"];
	$playerid = $_GET["PayerID"];
	$paypal_data = '';
	
	// Get all items from the shopping cart, concatenate to the variable $paypal_data
	// $_SESSION['Items'] is an associative array
	foreach($_SESSION['Items'] as $key=>$item) 
	{
		$paypal_data .= '&L_PAYMENTREQUEST_0_QTY'.$key.'='.urlencode($item["quantity"]);
	  	$paypal_data .= '&L_PAYMENTREQUEST_0_AMT'.$key.'='.urlencode($item["price"]);
	  	$paypal_data .= '&L_PAYMENTREQUEST_0_NAME'.$key.'='.urlencode($item["name"]);
		$paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER'.$key.'='.urlencode($item["productId"]);
	}
	
	//Data to be sent to PayPal
	$padata = '&TOKEN='.urlencode($token).
			  '&PAYERID='.urlencode($playerid).
			  '&PAYMENTREQUEST_0_PAYMENTACTION='.urlencode("SALE").
			  $paypal_data.	
			  '&PAYMENTREQUEST_0_ITEMAMT='.urlencode($_SESSION["SubTotal"]).
              '&PAYMENTREQUEST_0_TAXAMT='.urlencode($_SESSION["Tax"]).
              '&PAYMENTREQUEST_0_SHIPPINGAMT='.urlencode($_SESSION["ShipCharge"]).
			  '&PAYMENTREQUEST_0_AMT='.urlencode($_SESSION["SubTotal"] + 
			                                     $_SESSION["Tax"] + 
								                 $_SESSION["ShipCharge"]).
			  '&PAYMENTREQUEST_0_CURRENCYCODE='.urlencode($PayPalCurrencyCode);
	
	//We need to execute the "DoExpressCheckoutPayment" at this point 
	//to receive payment from user.
	$httpParsedResponseAr = PPHttpPost('DoExpressCheckoutPayment', $padata, 
	                                   $PayPalApiUsername, $PayPalApiPassword, 
									   $PayPalApiSignature, $PayPalMode);
	
	//Check if everything went ok..
	if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || 
	   "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) 
	{
		// Update stock inventory in product table 
		// after successful checkout
		$qry = "SELECT * FROM ShopCartItem WHERE ShopCartID=?";
		$stmt = $conn->prepare($qry);
		$stmt ->bind_param("i",$_SESSION["Cart"]); // "i" - integer
		$stmt ->execute();
		$result = $stmt->get_result();
		$stmt->close();
		while ($row = $result->fetch_array()){
			// reduce the number of stock
			$qry = "UPDATE product
					SET Quantity = Quantity - ?
					WHERE ProductID = ?";
			$stmt = $conn->prepare($qry);
			$stmt ->bind_param("ii", $row["Quantity"],$row["ProductID"]); // "i" - integer
			$stmt ->execute();
			$stmt->close();
		}
	
		//Update shopcart table, close the shopping cart (OrderPlaced=1)
		$total = $_SESSION["SubTotal"] + $_SESSION["Tax"] + $_SESSION["ShipCharge"];
		$qry = "UPDATE shopcart SET OrderPlaced=1, Quantity=?,
				SubTotal=?, ShipCharge=?,Tax=?,Total=? WHERE ShopCartID=?";

		$stmt = $conn->prepare($qry);
		// "i" - integer,"d" - double
		$stmt-> bind_param("iddddi",$_SESSION["NumCartItem"],$_SESSION["SubTotal"],$_SESSION["ShipCharge"],$_SESSION["Tax"],$total,$_SESSION["Cart"]);
		$stmt->execute();
		$stmt->close();
		
		//We need to execute the "GetTransactionDetails" API Call at this point 
		//to get customer details
		$transactionID = urlencode(
		                 $httpParsedResponseAr["PAYMENTINFO_0_TRANSACTIONID"]);
		$nvpStr = "&TRANSACTIONID=".$transactionID;
		$httpParsedResponseAr = PPHttpPost('GetTransactionDetails', $nvpStr, 
		                                   $PayPalApiUsername, $PayPalApiPassword, 
										   $PayPalApiSignature, $PayPalMode);

		if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || 
		   "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) 
		   {
			//gennerate order entry and feed back orderID information
			//You may have more information for the generated order entry 
			//if you set those information in the PayPal test accounts.
			
			$ShipName = addslashes(urldecode($httpParsedResponseAr["SHIPTONAME"]));
			
			$ShipAddress = urldecode($httpParsedResponseAr["SHIPTOSTREET"]);
			if (isset($httpParsedResponseAr["SHIPTOSTREET2"]))
				$ShipAddress .= ' '.urldecode($httpParsedResponseAr["SHIPTOSTREET2"]);
			if (isset($httpParsedResponseAr["SHIPTOCITY"]))
			    $ShipAddress .= ' '.urldecode($httpParsedResponseAr["SHIPTOCITY"]);
			if (isset($httpParsedResponseAr["SHIPTOSTATE"]))
			    $ShipAddress .= ' '.urldecode($httpParsedResponseAr["SHIPTOSTATE"]);
			$ShipAddress .= ' '.urldecode($httpParsedResponseAr["SHIPTOCOUNTRYNAME"]). 
			                ' '.urldecode($httpParsedResponseAr["SHIPTOZIP"]);
				
			$ShipCountry = urldecode(
			               $httpParsedResponseAr["SHIPTOCOUNTRYNAME"]);
			
			$ShipEmail = urldecode($httpParsedResponseAr["EMAIL"]);			
			
			// Insert an Order record with shipping information
			// Get the Order ID and save it in session variable.
			$qry = "INSERT INTO orderdata (ShipName, ShipAddress, ShipCountry, ShipEmail, ShopCartID, DeliveryMode)
					VALUES(?, ?, ?, ?, ?, ?)";
			$stmt = $conn->prepare($qry);
			// "i" - integer, "s"- string
			$stmt->bind_param("ssssis",$ShipName,$ShipAddress,$ShipCountry,$ShipEmail,$_SESSION["Cart"], $_SESSION["DeliveryType"]);
			$stmt->execute();
			$stmt->close();
			$qry = "SELECT LAST_INSERT_ID() AS OrderID";
			$result = $conn->query($qry);
			$row = $result->fetch_array();
				
			$conn->close();
			
			$_SESSION["OrderID"] = $row["OrderID"];
			$_SESSION["ShipAddress"] = $ShipAddress;
			$_SESSION["Total"] = $total;
				  
			// Reset the "Number of Items in Cart" session variable to zero.
			$_SESSION["NumCartItem"] = 0;
	  		
			// Clear the session variable that contains Shopping Cart ID.
			unset($_SESSION["Cart"]);
			
			// To Do 4C: Redirect shopper to the order confirmed page.
			header("Location: orderConfirmed.php");
		} 
		else 
		{
		    echo "<div style='color:red'><b>GetTransactionDetails failed:</b>".
			                urldecode($httpParsedResponseAr["L_LONGMESSAGE0"]).'</div>';
			echo "<pre>".print_r($httpParsedResponseAr)."</pre>";
			$conn->close();
		}
	}
	else {
		echo "<div style='color:red'><b>DoExpressCheckoutPayment failed : </b>".
		                urldecode($httpParsedResponseAr["L_LONGMESSAGE0"]).'</div>';
		echo "<pre>".print_r($httpParsedResponseAr)."</pre>";
	}
}

include("footer.php"); // Include the Page Layout footer
?>