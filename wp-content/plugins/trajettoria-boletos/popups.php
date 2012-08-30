<?php

require_once('../../../wp-load.php');
		
global $wpdb;
global $wp_query;

$popup = $_GET["popup"];
switch ($popup) {
	case 'cliente':
		$cpf = $_GET["cpf"];
		$boletos = $wpdb->get_results( "SELECT * 
										FROM traj_boletos 
										WHERE cpf = $cpf" , 
										OBJECT );

?>

		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h3 id="generic-modalLabel"><?php echo $boletos[0]->nome; ?></h3>
		</div>
		<div class="modal-body">
			<div class="endereco">
				<div class="row-fluid">
					<span class="span6">CPF:</span>
				</div>
				<div class="row-fluid">
					<span class="span6">Endereço:</span>
				</div>
				<div class="row-fluid">
					<span class="span3">Tel:</span> <span class="span3">Cel:</span>
				</div>
			</div>
			<div class="resumo-pedidos">
				<div class="row-fluid">
					<span class="span6">Data do primeiro pedido:</span>
				</div>
				<div class="row-fluid">
					<span class="span6">Total de pedidos:</span>
				</div>
			</div>
			
			<table class="table table-striped table-condensed table-custom-padding table-popup" >
				<thead>
					<tr class="bol-thead">
						<th class="col-head col-nossonumero <?php if($order=="nosso_numero" && $sort=="asc") echo "sort-asc"; elseif($order=="nosso_numero" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "nosso_numero"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Nosso Número</a>
						</th>
						<th class="col-head col-data <?php if($order=="data_criacao" && $sort=="asc") echo "sort-asc"; elseif($order=="data_criacao" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "data_criacao"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Dt. Emissão</a>
						</th>
						<th class="col-head col-data <?php if($order=="data_vencimento" && $sort=="asc") echo "sort-asc"; elseif($order=="data_vencimento" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "data_vencimento"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Dt. Venc.</a>
						</th>
						<th class="col-head col-servico <?php if($order=="servico" && $sort=="asc") echo "sort-asc"; elseif($order=="servico" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "serviço"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Serviço</a>
						</th>
						<th class="col-head col-status <?php if($order=="status_boleto" && $sort=="asc") echo "sort-asc"; elseif($order=="status_boleto" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_boleto"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><abbr title="Status do boleto">B</abbr></a>
						</th>
						<th class="col-head col-status <?php if($order=="status_pedido" && $sort=="asc") echo "sort-asc"; elseif($order=="status_pedido" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
							<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_pedido"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><abbr title="Status do pedido">P</abbr></a>
						</th>
						<th class="col-head input-medium">
							<span>Opções</span>
						</th>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
		</div>
		<div class="modal-footer">
			<button class="btn btn-primary close-modal" data-dismiss="modal" aria-hidden="true">Fechar</button>
		</div>

<?php
		break;
	case 'pedido':
?>
		<p>Gerar template para popup pedido</p>
<?php
		break;
	default:
		
		break;
}

?>