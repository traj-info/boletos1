<?php

require_once('../../../wp-load.php');
		
global $wpdb;
global $wp_query;

$popup = $_GET["popup"];
switch ($popup) {
	case 'cliente':
		$cpf = $_GET["cpf"];
		$boletos = $wpdb->get_results( "SELECT * 
										FROM " . TrajettoriaBoletos::TRAJ_BOLETOS_TABLE . " 
										WHERE cpf = $cpf
										ORDER BY data_criacao ASC" , 
										OBJECT );
										
		$totalBoletos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) 
														 FROM " . TrajettoriaBoletos::TRAJ_BOLETOS_TABLE . "
														 WHERE cpf = $cpf" ) );
		
		$somaPedidos = $wpdb->get_var( $wpdb->prepare( "SELECT SUM( valor ) FROM " . TrajettoriaBoletos::TRAJ_BOLETOS_TABLE . " WHERE cpf = $cpf" ) );

?>

		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h3 id="generic-modalLabel"><?php echo $boletos[0]->nome; ?></h3>
		</div>
		<div class="modal-body">
			<div class="endereco">
				<div class="row-fluid">
					<span class="span4">CPF: <?php echo $boletos[0]->cpf; ?></span>
					<span class="span4">Tel: <?php echo $boletos[0]->telefone; ?></span>
					<span class="span4">Cel: <?php echo $boletos[0]->celular; ?></span>
				</div>
				<div class="row-fluid">
					<span class="span12">Endereço: <?php echo $boletos[0]->endereco; ?></span>
				</div>
			</div>
			<div class="resumo-pedidos">
				<div class="row-fluid">
					<span class="span6">Data do primeiro pedido: <?php echo date_to_br( $boletos[0]->data_criacao ); ?></span>
					<span class="span6">Total de pedidos: R$<?php echo number_format( $somaPedidos, 2, ',', '.' ); ?></span>
				</div>
			</div>
			
			<table class="table table-striped table-condensed table-custom-padding table-popup" >
				<thead>
					<tr class="bol-thead">
						<th class="col-head col-nossonumero">
							<span>Nosso Número</span>
						</th>
						<th class="col-head col-data">
							<span>Emissão</span>
						</th>
						<th class="col-head col-data">
							<span>Dt. Venc.</span>
						</th>
						<th class="col-head col-servico">
							<span>Serviço</span>
						</th>
						<th class="col-head col-status">
							<span><abbr 
title="Status do boleto:
0 = Aberto
1 = Cancelado
2 = Pago
3 = Vencido">B</abbr>		</span>
						</th>
						<th class="col-head col-status">
							<span><abbr 
title="Status do pedido:
0 = Aguardando
1 = Em execução
2 = Finalizado">P</abbr>	</span>
						</th>
						<th class="col-head input-medium">
							<span>Opções</span>
						</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $boletos as $bol ) { ?>
					<tr>
						<td class="col-nossonumero"><?php echo $bol->nosso_numero; ?></td>
						<td class="col-data"><?php echo substr_replace( date_to_br( $bol->data_criacao ), "", 10 ); ?></td>
						<td class="col-data"><?php echo substr_replace( date_to_br( $bol->data_vencimento ), "", 10 ); ?></td>
						<td class="col-servico"><?php echo $bol->servico; ?></td>
						<td class="col-status"><?php echo $bol->status_boleto; ?></td>
						<td class="col-status"><?php echo $bol->status_pedido; ?></td>
						<td class="input-medium"></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
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