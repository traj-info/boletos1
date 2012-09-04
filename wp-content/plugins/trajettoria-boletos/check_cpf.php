<?php

require_once('includes/util.php');
		
$cpf = $_GET["cpf"];
$cpf = str_replace(".", "" ,$cpf);
$cpf = str_replace("-", "" ,$cpf);
if(check_cpf($cpf)) echo 'ok';
else echo 'erro';

?>