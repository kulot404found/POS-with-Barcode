<?php
require_once 'config.php';
require_login();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Barcode Scanner</title></head>
<body>
<h2>Barcode Scanner</h2>
<p>Scan an item barcode (scanner acts like keyboard) and press Enter. Then click "Sell" to record a single sale.</p>
<form action="pos.php" method="post" target="_self">
  <input type="text" name="barcode" id="scan_input" placeholder="Scan barcode here" autofocus>
  <input type="hidden" name="qty" value="1">
  <button type="submit">Sell</button>
</form>
</body>
</html>
