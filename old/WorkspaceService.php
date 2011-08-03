<?php
function echoo($echo){
	return "ECHO: " . $echo;
}

$soapServer = new SoapServer(null, array('uri' => "http://test"));
$soapServer->addFunction('echoo');
$soapServer->handle();
?>