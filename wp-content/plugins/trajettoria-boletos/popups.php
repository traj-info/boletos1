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
			<button type="button" class="close close-modal" data-dismiss="modal" aria-hidden="true">×</button>
			<h3 id="generic-modalLabel"><?php echo $boletos[0]->nome; ?></h3>
		</div>
		<div class="modal-body">
			<div class="resumo resumo-cliente">
				<div class="row-fluid">
					<span class="span4">CPF: <?php echo $boletos[0]->cpf; ?></span>
					<span class="span4">Tel: <?php echo $boletos[0]->telefone; ?></span>
					<span class="span4">Cel: <?php echo $boletos[0]->celular; ?></span>
				</div>
				<div class="row-fluid">
					<span class="span12">Endereço: <?php echo $boletos[0]->endereco; ?></span>
				</div>
			</div>
			<div class="resumo resumo-pedido">
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
						<td class="data bol-opcoes">
							<select name="bol-single[<?php echo $bol->id; ?>]" class="opcao bol-opcao">
								<option value="selecione">Selecione</option>
								<optgroup label="Mudar status do boleto:">
									<option value="pago_<?php echo $bol->id; ?>">Pago</option>
									<option value="nao-pago_<?php echo $bol->id; ?>">Aberto</option>
									<option value="cancelar_<?php echo $bol->id; ?>">Cancelado</option>
								</optgroup>
								<optgroup label="Mudar status do pedido:">
									<option value="nao-iniciado_<?php echo $bol->id; ?>">Aguardando</option>
									<option value="em-execucao_<?php echo $bol->id; ?>">Em execução</option>
									<option value="finalizado_<?php echo $bol->id; ?>">Finalizado</option>
								</optgroup>
								<option value="ver_<?php echo $bol->id; ?>">Ver boleto</option>
								<option value="pedido_<?php echo $bol->id; ?>">Ver pedido</option>
								<option value="enviar_<?php echo $bol->id; ?>">Enviar para cliente</option>
								<option value="segunda-via_<?php echo $bol->id; ?>">Gerar segunda via</option>
								<option value="excluir_<?php echo $bol->id; ?>">Excluir</option>
							</select>
						</td>
					</tr>
				<?php } ?>
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
		<div class="modal-header">
			<button type="button" class="close close-modal" data-dismiss="modal" aria-hidden="true">×</button>
			<h3 id="generic-modalLabel">Pedido #{numero_pedido}</h3>
		</div>
		<div class="modal-body">
			<div class="resumo resumo-cliente">
				<div class="linha">
					<div class="etiqueta">Serviço:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Cliente:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">CPF:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Endereço:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Tel:</div><div class="conteudo"></div><div class="etiqueta">Cel:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Data de emissão:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Data de vencimento:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<div class="etiqueta">Valor:</div><div class="conteudo">R$</div>
				</div>
				<div class="linha">
					<div class="etiqueta">Taxa do boleto:</div><div class="conteudo">R$</div>
				</div>
				<div class="linha">
					<div class="etiqueta">Descrição:</div><div class="conteudo"></div>
				</div>
				<div class="linha">
					<a href="#">Ver boleto</a>
				</div>
			</div>
			<div class="resumo resumo-pedido">
				
			</div>
			<div class="resumo resumo-arquivos">
				
			</div>
		</div>
		<div class="modal-footer">
			<button class="btn btn-primary close-modal" data-dismiss="modal" aria-hidden="true">Fechar</button>
		</div>
<?php
		break;
	default:
		
		break;
}

?>