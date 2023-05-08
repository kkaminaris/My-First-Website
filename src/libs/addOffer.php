<?php
session_start();
include '../libs/connection.php';
// Now we check if the data was submitted, isset() function will check if the data exists.
if (!isset($_POST['product'], $_POST['price'])) {
	// Could not get the data that should have been sent.
  $_SESSION['error'] = 'Data could not be sent, please try again.';
  header('Location: ../../public/addOffer.php?poiId=' . $_GET['poiId']);
  $con->close();
  exit;
}
// Make sure the submitted registration values are not empty.
if (empty($_POST['product']) || empty($_POST['price'])) {
	// One or more values are empty.
 	$_SESSION['error'] = 'Please fill in all fields.';
	header('Location: ../../public/addOffer.php?poiId=' . $_GET['poiId']);
	$con->close();
  exit;
}
//Make sure the user entered a numeric price
if (!is_numeric($_POST['price'])) {
 	$_SESSION['error'] = 'Please enter a valid price.';
	header('Location: ../../public/addOffer.php?poiId=' . $_GET['poiId']);
	$con->close();
  exit;
}

//Perform DayCheck
$yesterday = date("Y-m-d", strtotime('-1 days'));
$stmt = $con->prepare('SELECT AVG(Price) AS Price FROM offers WHERE Product_Id=? AND Is_active=1 AND Has_Stock=1 AND Date BETWEEN ? AND ? ; ');
$stmt->bind_param('sss', $_POST['product'], $yesterday, $yesterday);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
  if(!$row['Price']){
    // Product has no offers
    $dayCheck = true;
  }
  else {
    // Product has at least one offer
    $dayCheck = ($_POST['price'] < round(0.8 * $row['Price'], 2));
  }
}
$stmt->close();

//Perform WeekCheck
$lastWeek = date("Y-m-d", strtotime('-7 days'));
$stmt = $con->prepare('SELECT AVG(Price) AS Price FROM offers WHERE Product_Id=? AND Is_active=1 AND Has_Stock=1 AND Date BETWEEN ? AND ? ; ');
$stmt->bind_param('sss', $_POST['product'], $lastWeek, $yesterday);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
  if(!$row['Price']){
    // Product has no offers
    $weekCheck = true;
  }
  else {
    // Product has at least one offer
    $weekCheck = ($_POST['price'] < round(0.8 * $row['Price'], 2));
  }
}

$stmt->close();

//Check for already existing offer
$stmt = $con->prepare('SELECT Price FROM offers WHERE Poi_Id=? AND Product_Id=? AND Is_Active=1 ;');
$stmt->bind_param('ss', $_GET['poiId'], $_POST['product']);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
  if(!$row['Price']){
    // There is no existing offer
    $existingCheck = false;
    $stmt->close();
    exit;
  }
  else {
    // There is an existing offer active
    if ($_POST['price'] < round(0.8 * $row['Price'], 2)) {
      // The proposed offer is good enough
      $stmt2 = $con->prepare('UPDATE offers SET Is_Active = 0 WHERE Poi_Id=? AND Product_Id=? AND Is_Active=1 ;');
      $stmt2->bind_param('ss', $_GET['poiId'], $_POST['product']);
      $stmt2->execute();
      $stmt2->close();
      $existingCheck = false;
      $stmt->close();
      exit;
    }
    else{
      // The proposed offer is not good enough
      $existingCheck = true;
      $_SESSION['error'] = 'Υπάρχει ήδη προσφορά για αυτό το προϊόν σε αυτό το κατάστημα.';
      header('Location: ../../public/addOffer.php?poiId=' . $_GET['poiId']);
      $stmt->close();
      exit;
    }
  }
}

$stmt = $con->prepare('INSERT INTO offers (Poi_Id, User_Id, Product_Id, Price, Day_Check, Week_Check) VALUES (?, ?, ?, ?, ?, ?); ');
$stmt->bind_param('ssssss', $_GET['poiId'], $_SESSION['userId'], $_POST['product'],  $_POST['price'],$dayCheck, $weekCheck);
$stmt->execute();
$_SESSION['error'] = 'Η προσφορά καταχωρήθηκε! Ευχαριστούμε πολύ.';
header('Location: ../../public/addOffer.php?poiId=' . $_GET['poiId']);

$stmt->close();

$con->close();
?>