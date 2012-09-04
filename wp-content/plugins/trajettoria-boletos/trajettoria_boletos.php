<?php
/*
Plugin Name: Trajettoria Boletos
Plugin URI: http://www.trajettoria.com
Description: Plugin para emissao e gerenciamento de boletos bancarios
Author: Trajettoria
Version: 1.0
Author URI: http://www.trajettoria.com
*/

// includes
require_once('includes/util.php');
require_once('includes/class.phpmailer.php');
require_once('includes/class.smtp.php');
require_once('includes/class-base.php');
require_once('includes/class-options.php');
require_once('includes/class-setup.php');
require_once('includes/class-post_types.php');


class TrajettoriaBoletos extends WP_Plugin_Setup {
	
	// constants
	const STATUS_BOLETO_EM_ABERTO = 0;
	const STATUS_BOLETO_CANCELADO = 1;
	const STATUS_BOLETO_PAGO = 2;
	const STATUS_BOLETO_VENCIDO = 3;

	const STATUS_PEDIDO_NAO_INICIADO = 0;
	const STATUS_PEDIDO_EM_EXECUCAO = 1;
	const STATUS_PEDIDO_FINALIZADO = 2;

	const TRAJ_BOLETOS_TABLE = 'traj_boletos';
	const TRAJ_MAX_UPLOAD_SIZE = 20971520; // 20 MB
	
	const TRAJ_DS = '.'; // decimal separator

	
	###############################################################################################################

	# FUNCTION: __construct
	# DESCRIPTION: construtor
	
    public function __construct()
	{
        parent::__construct(); 
		$this->initialize();
    }
	
	
	###############################################################################################################

	# FUNCTION: install
	# DESCRIPTION: chamada na ativação do plugin do WordPres
	public static function install() 
	{

	}
	
	###############################################################################################################

	# FUNCTION: uninstall
	# DESCRIPTION: chamada na desativação do plugin do WordPres
	public static function uninstall() 
	{
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
	
	###############################################################################################################

	# FUNCTION: initialize
	# DESCRIPTION: chamada toda vez que o site é carregado com o plugin ativo. Prepara o ambiente de execução.
	public function initialize()
	{
		if(!is_admin())
		{
			// shortcodes
			$this->addShortcode('trajettoria-pedido', 'pagina_pedido');
			$this->addShortcode('trajettoria-painel-boletos', 'pagina_painel_boletos');
			$this->addShortcode('trajettoria-segunda-via', 'pagina_segunda_via');
			
			// css 
			wp_register_style( 'bootstrap-css', plugins_url( '/css/bootstrap.css', __FILE__ ), FALSE );
			wp_enqueue_style( 'bootstrap-css' );
			wp_register_style( 'boletos-style-css', plugins_url( '/css/style-boletos.css', __FILE__ ), FALSE );
			wp_enqueue_style( 'boletos-style-css' );
			
			// js
			wp_register_script( 'bootstrap-js', plugins_url( '/js/bootstrap.js', __FILE__ ), array( 'jquery' ) );
			wp_enqueue_script( 'bootstrap-js' );
			wp_register_script( 'jquery-form-js', plugins_url( '/js/jquery.form.2.67.js', __FILE__ ), array( 'jquery' ) );
			wp_enqueue_script( 'jquery-form-js' );
			wp_register_script( 'jquery-masked-input-js', plugins_url( '/js/jquery.maskedinput-1.3.js', __FILE__ ), array( 'jquery' ) );
			wp_enqueue_script( 'jquery-masked-input-js' );
			wp_register_script( 'jquery-validate-js', plugins_url( '/js/jquery.validate.js', __FILE__ ), array( 'jquery', 'jquery-form-js' ) );
			wp_enqueue_script( 'jquery-validate-js' );
			// TODO: use tinyMCE for textareas
		
			// query vars
			$this->addFilter('query_vars', 'query_vars');
			
			// template redirect
			$this->addAction('template_redirect', 'template_redirect');
		}
		

	}


	###############################################################################################################

	# FUNCTION: template_redirect
	# DESCRIPTION: redireciona para o template de "Serviços" e "Ver-Boleto"
	public function template_redirect()
	{
		global $wpdb;
		
        $page = get_query_var('trajettoria_page');
        if( $page === 'servicos' )
		{
            require_once('template-servicos.php');
            exit();
        }
		else if ( $page === 'get_boleto' )
		{
			$key = get_query_var('key');
			$nosso_numero = get_query_var('nosso_numero');
			$cpf = get_query_var('cpf');
			
			// aceita somente KEY OU (NOSSO NUMERO + CPF)
			if(!empty($key) && (!empty($nosso_numero) && !empty($cpf)))
			{
				exit('Acesso negado.');
			}
			
			// fornecida KEY
			if(!empty($key))
			{
				if(strlen($key) != 36) exit('KEY incorreta');
				
				$b = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE key_boleto='$key' LIMIT 1"), ARRAY_A );
				
			}
			else	// fornecidos nosso número e CPF
			{
				$b = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE nosso_numero='$nosso_numero' AND cpf='$cpf' LIMIT 1"), ARRAY_A );
			}		
			
			if(!$b) exit('Boleto não localizado.');
			
			$nosso_numero = $b['nosso_numero'];
			$taxa_boleto = $b['taxa_boleto'];
			$data_venc = date_to_br($b['data_vencimento'], TRUE);
			$valor_cobrado = $b['valor'];
			$data_emissao = date_to_br($b['data_criacao'], TRUE);
			$sacado = $b['nome'] . " (CPF: " . $b['cpf'] . ")";
			$end1 = $b['endereco'];
			$end2 = $b['cidade'] . "/" . strtoupper($b['uf']) . " - CEP: " . $b['cep'];
			$demonstrativo1 = get_bloginfo('name') . " - PEDIDO #" . $b['nosso_numero'];
			$demonstrativo2 = "Valor cobrado: R$ " . number_format($b['valor'],2,",","") . " + taxa do boleto R$ " . number_format($b['taxa_boleto'],2,",","");
			$demonstrativo3 = "Data do pedido: " . date_to_br($b['data_criacao']);
			$instrucoes1 = " - Sr. Caixa, NÃO receber após o vencimento.";
			$instrucoes2 = " - Sr. Cliente, entrar em contato com " . get_bloginfo('name');
			$instrucoes3 = " para segunda via após o vencimento.";
			$instrucoes4 = "";
			
			$codigo_cliente = self::_get_setting('cc');
			$agencia = self::_get_setting('agencia');
			$carteira = self::_get_setting('carteira');
			$cedente_nome = self::_get_setting('cedente_nome');
			$cedente_cnpj = self::_get_setting('cedente_cnpj');
			$cedente_endereco1 = self::_get_setting('cedente_endereco1');
			$cedente_endereco2 = self::_get_setting('cedente_endereco2');
			$cedente_logotipo = self::_get_setting('cedente_logotipo');
				
			require_once('get_boleto.php');
            exit();
		}
    }
	
	###############################################################################################################

	# FUNCTION: pagina_pedido
	# DESCRIPTION: renderiza a página Pedido, shortcode trajettoria-pedido
	public static function pagina_pedido()
	{
		$prod_id = get_query_var('prod_id');
		$step = $_POST['hidden_step'];
		
		if(!isset($_POST['submit'])) $step = "1";
		
		switch($step)
		{
			case "1":
				$class_1 = "active";
				$class_2 = "";
				$class_3 = "";
				break;
			case "2":
				$class_1 = "";
				$class_2 = "active";
				$class_3 = "";
				break;
			case "3":
				$class_1 = "";
				$class_2 = "";
				$class_3 = "active";
				break;
			default:
				$prod_id = '';
				break;
		}

		?>
		<div class="alignright">
			<ul class="nav nav-pills novo-pedido-seletor">
			  <li class="<?php echo $class_1; ?>">ETAPA 1: Cadastro</li>
			  <li class="<?php echo $class_2; ?>">ETAPA 2: Revisão</li>
			  <li class="<?php echo $class_3; ?>">ETAPA 3: Boleto</li>
			</ul>
		</div>
		<div class="clear"></div>
		<?php
		
		
		if(!$s = self::_get_servico($prod_id))
		{
			echo "<h3>Serviço não encontrado.</h3>";
		}
		else // serviço encontrado
		{
			echo "<div class='alert alert-info alert-big'>";
			echo "<p><span class='left_label'>Serviço:</span> <strong>" . $s['title'] . "</strong></p>";
			echo "<p><span class='left_label'>Valor:</span> <strong>R$ " . $s['valor'] . "</strong></p>";
			echo "</div>";
			
			echo "<form enctype='multipart/form-data' method='post' action='' name='frmPedido' id='frmPedido' class='form-horizontal'>";
			echo "<input type='hidden' name='hidden_prod_id' id='hidden_prod_id' value='" . $prod_id . "' />";
			echo "<input type='hidden' name='data_vencimento' id='data_vencimento' value='" . $s['data_vencimento'] . "' />";
			echo "<input type='hidden' name='taxa' id='taxa' value='" . $s['taxa'] . "' />";
			echo "<input type='hidden' name='valor' id='valor' value='" . $s['valor'] . "' />";
			
			
			switch($step)
			{
				case "1": // cadastro
					self::_pagina_painel_boletos_step1($s);
					break;
					
				case "2": // revisão
					self::_pagina_painel_boletos_step2($s);
					break;
				
				case "3": // boleto
					self::_pagina_painel_boletos_step3($s);
					break;
			}
		}
	}
	
	###############################################################################################################

	# FUNCTION: _pagina_painel_boletos_step1
	# DESCRIPTION: renderiza a página Painel de Boletos, step 1 (cadastro)
	# recebe um array "$s" com as informações sobre o serviço solicitado
	private static function _pagina_painel_boletos_step1($s)
	{
		if(isset($_POST['old_data']))
		{
			$d = unserialize(urldecode($_POST['old_data']));
			?>
			<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery('#uf').val('<?php echo $d['uf']; ?>');
			});
			</script>
			
			<?php
		}
		else
		{
			$d = NULL;
		}
	?>
		<input type='hidden' name='hidden_step' id='hidden_step' value='2' />
		<input type='hidden' name='formatos_arquivo' id='formatos_arquivo' value='<?php echo $s['formatos_arquivo']; ?>' />
		<fieldset>

		<div class="control-group">
		<label class="control-label" for="nome">Nome:</label>
		<div class="controls">
			<input type="text" class="input-xlarge required" minlength="5" id="nome" name="nome" value="<?php echo $d['nome']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="cpf">CPF:</label>
		<div class="controls">
			<input type="text" class="input-medium required" id="cpf" name="cpf" value="<?php echo $d['cpf']; ?>">
			<label for="cpf" class="error" id="val-cpf"></label>
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="instituicao">Instituição:</label>
		<div class="controls">
			<input type="text" class="input-xlarge required" id="instituicao" name="instituicao" value="<?php echo $d['instituicao']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="email">E-mail:</label>
		<div class="controls">
			<input type="text" class="input-xlarge required email" id="email" name="email" value="<?php echo $d['email']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="telefone">Telefone:</label>
		<div class="controls">
			<input type="text" class="input-medium required" id="telefone" name="telefone" value="<?php echo $d['telefone']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="celular">Celular:</label>
		<div class="controls">
			<input type="text" class="input-medium required" id="celular" name="celular" value="<?php echo $d['celular']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="endereco">Endereço:</label>
		<div class="controls">
			<textarea name="endereco" id="endereco" minlength="10" class="input-xlarge required" rows="3" cols="150"><?php echo $d['endereco']; ?></textarea>
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="cep">CEP:</label>
		<div class="controls">
			<input type="text" class="input-small required" id="cep" name="cep" value="<?php echo $d['cep']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="cidade">Cidade:</label>
		<div class="controls">
			<input type="text" class="input-medium required" id="cidade" name="cidade" value="<?php echo $d['cidade']; ?>">
		</div>
		</div>
		
		<div class="control-group">
		<label class="control-label" for="uf">UF:</label>
		<div class="controls">
			<select name="uf" id="uf" class="input-medium required">
			<option value>Selecione...</option>
			<option value="ac">Acre</option>
			<option value="al">Alagoas</option>
			<option value="am">Amazonas</option>
			<option value="ap">Amapá</option>
			<option value="ba">Bahia</option>
			<option value="ce">Ceará</option>
			<option value="df">Distrito Federal</option>
			<option value="es">Espírito Santo</option>
			<option value="go">Goiás</option>
			<option value="ma">Maranhão</option>
			<option value="mt">Mato Grosso</option>
			<option value="ms">Mato Grosso do Sul</option>
			<option value="mg">Minas Gerais</option>
			<option value="pa">Pará</option>
			<option value="pb">Paraíba</option>
			<option value="pr">Paraná</option>
			<option value="pe">Pernambuco</option>
			<option value="pi">Piauí</option>
			<option value="rj">Rio de Janeiro</option>
			<option value="rn">Rio Grande do Norte</option>
			<option value="ro">Rondônia</option>
			<option value="rs">Rio Grande do Sul</option>
			<option value="rr">Roraima</option>
			<option value="sc">Santa Catarina</option>
			<option value="se">Sergipe</option>
			<option value="sp">São Paulo</option>
			<option value="to">Tocantins</option>
			</select>
		</div>
		</div>
		
		
		<div class="control-group">
		<label class="control-label" for="descricao"><?php echo $s['label_descricao']; ?>:</label>
		<div class="controls">
			<textarea name="descricao" id="descricao" class="input-xxlarge required" rows="5" cols="150"><?php echo $d['descricao']; ?></textarea>
		</div>
		</div>
		
		<?php if ($s['permitir_upload']) : ?>
		
		<div class="control-group">
		<label class="control-label" for="arquivos[]">Envio de arquivos:</label>
		<div class="controls">
			<p class="help-block">Selecione o(s) arquivo(s) necessário(s) para a análise estatística.<br/>
			Você pode selecionar até 5 arquivos.<br/>
			Nós recomendamos agrupá-los num único arquivo compactado.<br/>
			São aceitos os formatos: <strong><?php echo $s['formatos_arquivo']; ?></strong>.</p>
			<div id="arquivos-holder">
				<div class='linha-arquivo'>
					<input class="input-file" id="arquivos[]" name="arquivos[]" type="file"><button class='btn btn-danger remover-arquivo' onclick="return false;"><i class="icon-trash icon-white"></i> remover</button>
				</div>
			</div>
		<br />
		<button class='btn btn-success' id='novo-arquivo' onclick='return false;'><i class="icon-plus icon-white"></i> adicionar outro arquivo...</button>					
		</div>
		</div>
		
		<script type="text/javascript">
		jQuery(document).ready(function(){
		
			// File upload inputs
			jQuery('#novo-arquivo').click(function(){
				cont_arq = jQuery('.linha-arquivo').length;
				cont_arq++;
				if(cont_arq < 6) {
					jQuery("<div class='linha-arquivo'><input class='input-file' id='arquivos[]' name='arquivos[]' type='file'><button class='btn btn-danger remover-arquivo' onclick='return false;'><i class='icon-trash icon-white'></i> remover</button></div>").appendTo(jQuery('#arquivos-holder'));

					// Remove file
					jQuery('.remover-arquivo').click(function(){
						jQuery(this).parent('.linha-arquivo').remove();
						cont_arq = jQuery('.linha-arquivo').length;
						if(cont_arq < 6)
						{
							jQuery('#novo-arquivo').show();
						}
					});
					
					// File types validation
					jQuery('.input-file').change(function(){
						var extension = getExtension(jQuery(this).val());
						var allowed = '<?php echo $s['formatos_arquivo']; ?>';
						var allowed_array = allowed.split(",");
						
						if(jQuery.inArray(extension, allowed_array) == -1) // ext not allowed
						{
							jQuery(this).parent('.linha-arquivo').remove();
							cont_arq = jQuery('.linha-arquivo').length;
							if(cont_arq < 6)
							{
								jQuery('#novo-arquivo').show();
							}
						}
					});
			
					
					if(cont_arq == 5) jQuery('#novo-arquivo').hide();
				}
			});
			jQuery('.remover-arquivo').click(function(){
				jQuery(this).parent('.linha-arquivo').remove();
				cont_arq = jQuery('.linha-arquivo').length;
				if(cont_arq < 6)
				{
					jQuery('#novo-arquivo').show();
				}
			});
			
			// Validate
			jQuery('#frmPedido').validate({
				messages: {
					nome: 'Digite seu nome',
					email: {
						required: 'Digite seu e-mail',
						email: 'Digite um e-mail válido'
					},
					cpf: 'Digite seu CPF no formato 99.999.999-9',
					instituicao: 'Digite o nome da instituição à qual esteja vinculado',
					telefone: 'Digite seu telefone',
					celular: 'Digite seu celular',
					endereco: 'Digite seu endereço completo, incluindo complementos',
					cep: 'Digite seu CEP no formato 99999-999',
					cidade: 'Digite o nome de sua cidade',
					uf: 'Selecione seu Estado',
					descricao: 'Digite a descrição dos serviços solicitados'
				}
			});

			// CPF validation
			jQuery('#cpf').blur(function(){
				var cpf = jQuery(this).val();
				
				jQuery.ajax({
					url: "<?php echo plugins_url("check_cpf.php",__FILE__); ?>?cpf=" + cpf,
					dataType: "html"
				}).done(function(data){
					if(data == 'ok')
					{
						jQuery("#val-cpf").hide();
					}
					else
					{
						jQuery("#cpf").val('');
						jQuery("#val-cpf").html('Digite um CPF válido.');
						jQuery("#val-cpf").show();
					}
				});
			});
			
			// File types validation
			jQuery('.input-file').change(function(){
			
				var extension = getExtension(jQuery(this).val());
				var allowed = '<?php echo $s['formatos_arquivo']; ?>';
				var allowed_array = allowed.split(",");
				
				if(jQuery.inArray(extension, allowed_array) == -1) // ext not allowed
				{
					jQuery(this).parent('.linha-arquivo').remove();
					cont_arq = jQuery('.linha-arquivo').length;
					if(cont_arq < 6)
					{
						jQuery('#novo-arquivo').show();
					}
				}
			});
			
			// Input masks
			jQuery('#cpf').mask('999.999.999-99');
			jQuery('#telefone').mask('(99) 99999999? ********************');
			jQuery('#celular').mask('(99) 99999999?9');
			jQuery('#cep').mask('99999-999');
			
			// Popover endereço
			jQuery('#endereco').popover({
				trigger: "focus",
				title: "Exemplo:",
				content: "<h4>Av. Paulista, 2200 - cj. 161</h4>"
			})
			
		
		});
		
		function redireciona(url)
		{
			window.location.href = url;
		}
		
		function getExtension(filename)
		{
			return filename.split('.').pop().toLowerCase();
		}
		
		</script>
		
		<?php endif; ?>
		
		<?php
		echo "<div class='form-actions'>";
		echo "<button type='submit' name='submit' id='submit' class='btn btn-primary'>Próximo >></button>";
		echo "<a name='cancel' id='cancel' class='small-link' href='" .  get_site_url() . "'>Cancelar</a>";
		echo "</div>";
		echo "</fieldset>";
		echo "</form>";
	}	
	
	###############################################################################################################

	# FUNCTION: _pagina_painel_boletos_step2
	# DESCRIPTION: renderiza a página Painel de Boletos, step 2 (revisão dos dados)
	# recebe um array "$s" com as informações sobre o serviço solicitado
	private static function _pagina_painel_boletos_step2($s)
	{
		// save posted form data in case user wants to go back to fix some data previously entered.
		$old_data = urlencode(serialize($_POST));
		
		// processar uploads
		$arquivos = self::_process_uploads(explode(",", $s['formatos_arquivo']), self::TRAJ_MAX_UPLOAD_SIZE);
		$arquivos = is_array($arquivos) ? implode(",", $arquivos) : '';
		
		?>
		<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery('#submit_previous').click(function(){
				jQuery('#hidden_step').val('1');
			});
		});
		</script>
		
		<input type='hidden' name='hidden_step' id='hidden_step' value='3' />
		<input type='hidden' name='old_data' id='old_data' value='<?php echo $old_data; ?>' />
		<input type='hidden' name='arquivos' id='arquivos' value='<?php echo $arquivos; ?>' />
		
		<fieldset>
		
		<p><strong>Revise as informações abaixo antes de concluir seu pedido.</strong></p>
		<p>Se quiser efetuar alguma correção, clique em "<< Anterior".</p>
		
		<div class="rev-holder">
			<div class="rev-label">Nome:</div>
			<div class="rev-data"><?php echo $_POST['nome']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">CPF:</div>
			<div class="rev-data"><?php echo $_POST['cpf']; ?></div>
		</div>
		<div class="clear"></div>		
		
		<div class="rev-holder">
			<div class="rev-label">Instituição:</div>
			<div class="rev-data"><?php echo $_POST['instituicao']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">E-mail:</div>
			<div class="rev-data"><?php echo $_POST['email']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Telefone:</div>
			<div class="rev-data"><?php echo $_POST['telefone']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Celular:</div>
			<div class="rev-data"><?php echo $_POST['celular']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Endereço:</div>
			<div class="rev-data"><?php echo $_POST['endereco']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">CEP:</div>
			<div class="rev-data"><?php echo $_POST['cep']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Cidade:</div>
			<div class="rev-data"><?php echo $_POST['cidade']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">UF:</div>
			<div class="rev-data"><?php echo $_POST['uf']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Descrição:</div>
			<div class="rev-data"><?php echo $_POST['descricao']; ?></div>
		</div>
		<div class="clear"></div>
		
		<div class="rev-holder">
			<div class="rev-label">Arquivo(s):</div>
			<div class="rev-data"><?php echo self::_list_files(); ?></div>
		</div>
		<div class="clear"></div>
		
		<?php
		echo "<div class='form-actions'>";
		echo "<button type='submit' name='submit' id='submit_previous' class='btn'><< Anterior</button>&nbsp;&nbsp;";
		echo "<button type='submit' name='submit' id='submit' class='btn btn-primary'>Próximo >></button>";
		echo "<a name='cancel' id='cancel' class='small-link' href='" .  get_site_url() . "'>Cancelar</a>";
		echo "</div>";
		echo "</fieldset>";
		echo "</form>";		
	}	
	
	###############################################################################################################

	# FUNCTION: _pagina_painel_boletos_step3
	# DESCRIPTION: renderiza a página Painel de Boletos, step 3 (boleto)
	# recebe um array "$s" com as informações sobre o serviço solicitado
	private static function _pagina_painel_boletos_step3($s)
	{
		global $wpdb;
	
		// obter valores postados
		$p = unserialize(urldecode($_POST['old_data']));
		$arquivos = $_POST['arquivos'];
		
		// obter valores calculados
		$key_boleto = create_guid();
		$ip = get_ip();
		$agora = NowDatetime();
		
		// obter nosso número
		$old_nn = self::_get_setting('ultimo_nosso_numero');
		if(!is_numeric($old_nn)) $old_nn = 0;
		$nosso_numero = (int)$old_nn + 1;
		update_option(WP_Plugin_Options::PREFIX . '_ultimo_nosso_numero', $nosso_numero);

		// preparar dados para BD
		$p['valor'] = str_replace(",", self::TRAJ_DS, $p['valor']);
		$p['taxa'] = str_replace(",", self::TRAJ_DS, $p['taxa']);
		
		// salvar registro no BD
		$dados = array(
			'post_id'		=> $p['hidden_prod_id'],
			'nome'			=> $p['nome'],
			'email'			=> $p['email'],
			'telefone'		=> $p['telefone'],
			'celular'		=> $p['celular'],
			'cpf'			=> $p['cpf'],
			'endereco'		=> $p['endereco'],
			'cep'			=> $p['cep'],
			'instituicao'	=> $p['instituicao'],
			'ip'			=> $ip,
			'cidade'		=> $p['cidade'],
			'uf'			=> $p['uf'],
			'arquivos'		=> $arquivos,
			'descricao'		=> $p['descricao'],
			'data_criacao'	=> $agora,
			'data_vencimento' => $p['data_vencimento'],
			'valor'			=> $p['valor'],
			'taxa_boleto'	=> $p['taxa'],
			'status_boleto'	=> self::STATUS_BOLETO_EM_ABERTO,
			'status_pedido'	=> self::STATUS_PEDIDO_NAO_INICIADO,
			'nosso_numero'	=> $nosso_numero,
			'key_boleto'	=> $key_boleto,
			'obs'			=> ''
		);
		
		if(! $wpdb->insert(self::TRAJ_BOLETOS_TABLE, $dados))
		{
			echo "Erro ao finalizar pedido. Tente novamente.";
		}
		
		// envio de e-mail para cliente
		$t = "<p><strong>" . get_bloginfo('name') . "</strong></p>";
		$t .= "<p><strong>Confirmação do pedido número $nosso_numero</strong></p>";
		$t .= "<hr>";
		$t .= "<p>Olá, " . $p['nome'] . "!<br />";
		$t .= "Recebemos seu pedido com sucesso! Guarde este e-mail pois ele contém informações importantes.</p>";
		$t .= "<p>Data do pedido: <strong>" . date_to_br($agora) . "</strong><br />";
		$t .= "Serviço: <strong>" . $s['title'] . "</strong><br />";
		$t .= "Valor: <strong> R$ " . number_format($p['valor'], 2, ",", "") . "</strong><br />";
		$t .= "Taxa do boleto: <strong> R$ " . number_format($p['taxa'], 2, ",", "") . "</strong><br />";
		$t .= "Data de vencimento do boleto: <strong>" . date_to_br($p['data_vencimento']) . "</strong><br />";
		$t .= "Seu CPF: <strong>" . $p['cpf'] . "</strong></p>";
		$t .= "<hr>";
		$t .= "<p><strong>Para imprimir seu boleto, copie-e-cole o seguinte endereço em seu navegador, ou clique no link:</strong><br/>";
		$t .= "<a href='" . get_site_url() . "/ver-boleto/?key=" . $key_boleto . "'>" . get_site_url() . "/ver-boleto/?key=" . $key_boleto . "</a></p>";
		$t .= "<br/><p>Atenciosamente,<br/>";
		$t .= "Equipe " . get_bloginfo('name') . "<br/>";
		$t .= "<a href='" . get_site_url() . "'>" . get_site_url() . "</a></p>";
		$subject = "Confirmação do pedido " . $nosso_numero;
		self::_send_mail($p['email'], $subject, $t);
		
		// envio de e-mail para admin
		$t = "<p><strong>" . get_bloginfo('name') . "</strong></p>";
		$t .= "<p><strong>Novo pedido número $nosso_numero</strong></p>";
		$t .= "<hr>";
		$t .= "<p>Data do pedido: <strong>" . date_to_br($agora) . "</strong><br />";
		$t .= "Serviço: <strong>" . $s['title'] . "</strong><br />";
		$t .= "Valor: <strong> R$ " . number_format($p['valor'], 2, ",", "") . "</strong><br />";
		$t .= "Taxa do boleto: <strong> R$ " . number_format($p['taxa'], 2, ",", "") . "</strong><br />";
		$t .= "Data de vencimento do boleto: <strong>" . date_to_br($p['data_vencimento']) . "</strong><br />";
		$t .= "Nome: <strong>" . $p['nome'] . "</strong><br />";
		$t .= "E-mail: <a href='mailto:" . $p['email'] . "'><strong>" . $p['email'] . "</strong></a><br />";
		$t .= "CPF: <strong>" . $p['cpf'] . "</strong><br />";
		$t .= "Instituição: <strong>" . $p['instituicao'] . "</strong><br />";
		$t .= "Telefone: <strong>" . $p['telefone'] . "</strong><br />";
		$t .= "Celular: <strong>" . $p['celular'] . "</strong><br />";
		$t .= "Endereço: <strong>" . $p['endereco'] . "</strong><br />";
		$t .= "CEP: <strong>" . $p['cep'] . "</strong><br />";
		$t .= "Cidade/UF: <strong>" . $p['cidade'] . "/" . strtoupper($p['uf']) . "</strong><br />";
		$t .= "Arquivo(s): <strong>" . $arquivos . "</strong><br />";
		$t .= "Descrição: <strong>" . $p['descricao'] . "</strong></p>";
		
		$t .= "<hr>";
		$t .= "<p><strong>Para imprimir o boleto, copie-e-cole o seguinte endereço em seu navegador, ou clique no link:</strong><br/>";
		$t .= "<a href='" . get_site_url() . "/ver-boleto/?key=" . $key_boleto . "'>" . get_site_url() . "/ver-boleto/?key=" . $key_boleto . "</a></p>";
		$t .= "<br/><p>Atenciosamente,<br/>";
		$t .= "Equipe " . get_bloginfo('name') . "<br/>";
		$t .= "<a href='" . get_site_url() . "'>" . get_site_url() . "</a></p>";
		$subject = "NOVO PEDIDO - " . $nosso_numero;
		self::_send_mail(self::_get_setting('email'), $subject, $t);
		//echo $t;
		
		// renderiza comprovante
		?>
		<h2 class="tit-confirmacao-pedido">Número do pedido: <span id="numero-pedido"><?php echo $nosso_numero; ?></span></h2>
		<h3>Recebemos com sucesso os detalhes de seu pedido. Se for necessário complementar alguma informação, entraremos em contato preferencialmente por e-mail.</h3>
		<div class="btn-holder-success"><a href="<?php echo get_site_url() . "/ver-boleto/?key=" . $key_boleto; ?>" target="_blank" class="btn btn-large btn-success" type="button" style="color: #fff !important;">Imprimir Boleto</a></div>
		<h4>Orientações importantes:</h4>
		<ul class="orientacoes">
			<li>Um e-mail foi enviado para <strong><?php echo $p['email']; ?></strong> contendo informações importantes. Guarde-o pois ele comprova a efetivação de seu pedido.</li>
			<li>Se não receber o e-mail, verifique se ele não está em sua caixa de SPAM por engano.</li>
			<li>O pedido só será confirmado após o pagamento do boleto bancário e a confirmação de pagamento pela instituição bancária. Demora em média 3 dias úteis entre o pagamento e a confirmação pelo banco.</li>
			<li><a href="<?php echo get_site_url() . "/ver-boleto/?key=" . $key_boleto; ?>" target="_blank"><strong>Clique aqui</strong></a> para imprimir seu boleto bancário. (Obs.: taxa de R$ <?php echo number_format($p['taxa'], 2, ",", ""); ?> por boleto.)</li>
			<li>Você também poderá imprimi-lo posteriormente através do link informado no e-mail que lhe foi enviado, ou informando seus dados na página de <a href="<?php echo get_site_url() . "/segunda-via-de-boleto/"; ?>">Segunda Via de Boleto</a>.</li>
			<li>Em caso de dúvidas ou problemas, <a href="<?php echo get_site_url() . "/fale-conosco/"; ?>">Fale Conosco</a>.</li>
		</ul>
		
		<?php
	}		
	
	###############################################################################################################

	# FUNCTION: pagina_painel_boletos
	# DESCRIPTION: renderiza a página Painel de Boletos, shortcode trajettoria-painel-boletos
	public static function pagina_painel_boletos()
	{
		global $wpdb;
		
		// abaixo: exemplos
		// echo self::_helper_boleto_link('JNDHF8Y4GRUFHDJF', '0087998', '35941385854'); 
		// $prod_id = get_query_var('prod_id');
		// echo "<br><br>prod_id = " . $prod_id;
		// echo "<br><br>valor da propriedade 'label': " . self::_get_setting('label_descricao');
		
		if ( current_user_can('manage_options') ) {
			
			$menu = '<div class="alignright">';
			$menu .= '	<ul class="nav nav-pills">';
			$menu .=		'<li '; 
			if( !isset($_GET['modo']) || $_GET['modo'] != 'clientes' ) { 
				$menu .= "class=active"; 
			}
			$menu .= '><a href="?modo=todos">Boletos</a></li>';
			$menu .=		'<li ';
			if( $_GET['modo'] == 'clientes' ) { 
				$menu .= "class=active"; 
			}
			$menu .= '><a href="?modo=clientes">Clientes</a></li>';
			$menu .=	'</ul>';
			$menu .= '</div>';
			
			echo $menu;
			
			/* conta quantos boletos existem sempre que a página é carregada 
			 * 
			 * se não estivermos filtrando por CPF, contamos o total geral de boletos
			 * senão, contamos os boletos do cliente do CPF em questão
			 * */
			if( !get_query_var("cpf") )
				$totalBoletos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::TRAJ_BOLETOS_TABLE ) );
			else
				$totalBoletos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE cpf=" . get_query_var("cpf") ) );
			
			/* conta quantos clientes existem sempre que a página é carregada 
			 * */
			$totalClientes = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(distinct cpf) FROM " . self::TRAJ_BOLETOS_TABLE ) );
			
			switch ( get_query_var('modo') ) {
				
				case 'clientes':					
					// preparando paginação
					if ( !isset($_GET['order_by']) ) {
						$order = 'cpf';
					} else {
						$order = get_query_var('order_by');
					}
					if ( !isset($_GET['sort']) ) {
						$sort = 'desc';
					} else {
						$sort = get_query_var('sort');
					}
					if ( !isset($_GET['limit']) ) {
						$limit = 20;
					} else {
						$limit = get_query_var('limit');
					}
					if ( !isset($_GET['offset']) || $_GET['offset'] < 0 ) {
						$offset = 0;
					} else {
						$offset = get_query_var('offset');
					}
					$clientes = $wpdb->get_results( "SELECT * FROM " . self::TRAJ_BOLETOS_TABLE . " GROUP BY cpf ORDER BY $order $sort LIMIT $limit OFFSET $offset", OBJECT_K );
					
					?>
					<form method="POST" enctype="multipart/form-data" action="" class="form-inline">
						<table class="table table-striped table-custom-padding" id="clientes-table" >
							<thead>
								<tr class="bol-tpagination">
									<th colspan="6" >
										<div class="row-fluid table-header">
											<div class="span4 center text">Mostrando clientes <?php echo $offset+1; ?> a <?php echo sizeof($clientes) + $offset; ?> de <?php echo $totalClientes; ?></div>
											<div class="span4 center pagination-custom">
												<ul>
													<li><a href="?modo=clientes&offset=0&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; ?>"><button class="btn btn-small btn-primary" type="button"><<</button></a></li>
													<li><a href="?modo=clientes&offset=<?php if ($offset-$limit-1 < 0) echo 0; else echo $offset-$limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; ?>"><button class="btn btn-small btn-primary" type="button"><</button></a></li>
													<li><input type="text" class="input-mini" id="ir-para-pagina" value="<?php echo floor($offset / $limit) + 1; ?>" /></li>
													<li><a href="?modo=clientes&offset=<?php if ($offset+$limit+1 > $totalClientes) echo $offset; else echo $offset+$limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; ?>"><button class="btn btn-small btn-primary" type="button">></button></a></a></li>
													<li><a href="?modo=clientes&offset=<?php if( $totalClientes % $limit == 0 ) echo $totalClientes - $limit; else echo $totalClientes - $totalClientes % $limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; ?>"><button class="btn btn-small btn-primary" type="button">>></button></a></a></li>
												</ul>
											</div>
											<div class="span4 center form-inline">
												<label for="boletos-por-pagina">Clientes por página:</label>
												<select name="limit" class="input-mini" id="boletos-por-pagina">
													<option class="active"><?php echo $limit; // @todo popular dinamicamente essa select com a quantidade real de páginas até no máximo 20 ?></option>
													<option value="1">1</option>
													<option value="5">5</option>
													<option value="10">10</option>
													<option value="15">15</option>
													<option value="20">20</option>
												</select>
											</div>
										</div> 
									</th>
								</tr>
								<tr class="bol-thead">
									<th class="col-head <?php if($order=="cpf" && $sort=="asc") echo "sort-asc"; elseif($order=="cpf" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=clientes&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "cpf"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; ?>">CPF</a>
									</th>
									<th class="col-head col-nome <?php if($order=="nome" && $sort=="asc") echo "sort-asc"; elseif($order=="nome" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=clientes&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "nome"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; ?>">Nome</a>
									</th>
									<th class="col-head <?php if($order=="email" && $sort=="asc") echo "sort-asc"; elseif($order=="email" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=clientes&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "email"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; ?>">E-mail</a>
									</th>
									<th class="col-head col-status-boleto <?php if($order=="status_boleto" && $sort=="asc") echo "sort-asc"; elseif($order=="status_boleto" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=clientes&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_boleto"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; ?>">Boleto em aberto?</a>
									</th>
									<th class="col-head col-status-pedido <?php if($order=="status_pedido" && $sort=="asc") echo "sort-asc"; elseif($order=="status_pedido" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=clientes&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_pedido"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; ?>">Pedido em aberto?</a>
									</th>
									<th class="col-head col-opcoes">
										<span>Opções</span>
									</th>
								</tr>
							</thead>
							<tbody>
				
								
							<?php foreach ( $clientes as $c ) { ?>
								<tr class='cliente-$c->id'>
									<td class="data cliente-cpf"><?php echo $c->cpf; ?></td>
									<td class="data cliente-nome"><?php echo $c->nome; ?></td>
									<td class="data cliente-email"><?php echo $c->email; ?></td>
									<td class="data cliente-statusbol">
										<?php 
											$totalBolAbertos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE cpf = " . $c->cpf . " AND status_boleto = " . self::STATUS_BOLETO_EM_ABERTO ) );
											if ( $totalBolAbertos > 0 ) {
												echo "Sim";
											} else {
												echo "Não";
											}
										?>
									</td>
									<td class="data cliente-statuspedido">
										<?php
											$totalPedAbertos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE cpf=" . $c->cpf . " AND ( status_pedido = " . self::STATUS_PEDIDO_EM_EXECUCAO . " OR status_pedido = " . self::STATUS_PEDIDO_NAO_INICIADO . " )" ) );
											if ( $totalPedAbertos > 0 ) {
												echo "Sim";
											} else {
												echo "Não";
											}
										?>
									</td>
									<td class="data cliente-opcoes">
										<select name="cliente-opcao[<?php echo $c->cpf; ?>]" class="opcao cliente-opcao">
											<option value="selecione">Selecione</option>
											<option value="boletos_<?php echo $c->cpf; ?>">Ver boletos/pedidos</option>
											<option value="dados_<?php echo $c->cpf; ?>">Ver dados pessoais</option>
										</select>
									</td>
								</tr>
							<?php } ?>
				
							</tbody>
						</table>
					</form>
					
					<?php 
					break;
				
				default:
					
					// tratando ação quick-change
					$msg["quick_change"] = "";
					if ( isset( $_POST['submit_quickchange'] ) ) {
						
						if ( preg_match( '/[^0-9]/', $_POST['nosso_numero'] ) == TRUE || $_POST['nosso_numero'] === "" ) {
							$msg["quick_change"] = '<div class="alert alert-error fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Por favor, preencha corretamente o campo "nosso número". Ele deve conter apenas números.</div>';
						} else {
							$totalBoletos = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE nosso_numero = {$_POST['nosso_numero']}" ) );
							if ($totalBoletos > 0) {
								
								switch ( $_POST['submit_quickchange'] ) {
									case 'Marcar como pago':
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_PAGO ), array( 'nosso_numero' => $_POST['nosso_numero'] ) );
										$msg["quick_change"] = '<div class="alert alert-success fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> foi marcado como pago!</div>';
										break;
									case 'Marcar como não pago':
										// checa se boleto venceu
										$statusBoleto = $wpdb->get_var( $wpdb->prepare( "SELECT status_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE nosso_numero = " . $_POST['nosso_numero'] ) );
										if( $statusBoleto != self::STATUS_BOLETO_VENCIDO ) {
											$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_EM_ABERTO ), array( 'nosso_numero' => $_POST['nosso_numero'] ) );
											$msg["quick_change"] = '<div class="alert alert-success fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> foi marcado como não-pago!</div>';
										} else {
											$msg["quick_change"] = '<div class="alert alert-error fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> passou da data de vencimento.</div>';
										}
										break;
									case 'Cancelar':
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_CANCELADO ), array( 'nosso_numero' => $_POST['nosso_numero'] ) );
										$msg["quick_change"] = '<div class="alert alert-success fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> foi cancelado!</div>';
										break;
									case 'Excluir':
										$wpdb->delete( self::TRAJ_BOLETOS_TABLE, array( 'nosso_numero' => $_POST['nosso_numero'] ) );
										$msg["quick_change"] = '<div class="alert alert-success fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> foi deletado!</div>';
										break;
									default:
										break;
								}
							} else {
								$msg["quick_change"] = '<div class="alert alert-error fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Boleto <strong>' . $_POST['nosso_numero'] . '</strong> inexistente...</div>';
							}
						}
					}
					
					// tratando ação bol-opcao
					if ( isset( $_POST['bol-single'] ) ) {
						
						foreach ( $_POST['bol-single'] as $bolID => $option ) {
							if( $option != "selecione" ) {
								switch ( $option ) {
									case "pago_$bolID":
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_PAGO ), array( 'id' => $bolID ) );
										break;
									case "nao-pago_$bolID":
										$statusBoleto = $wpdb->get_var( $wpdb->prepare( "SELECT status_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id = " . $bolID ) );
										if( $statusBoleto != self::STATUS_BOLETO_VENCIDO ) {
											$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_EM_ABERTO ), array( 'id' => $bolID ) );
										}
										break;
									case "cancelar_$bolID":
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_CANCELADO ), array( 'id' => $bolID ) );
										break;
									case "nao-iniciado_$bolID":
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_pedido' => self::STATUS_PEDIDO_NAO_INICIADO ), array( 'id' => $bolID ) );
										break;
									case "em-execucao_$bolID":
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_pedido' => self::STATUS_PEDIDO_EM_EXECUCAO ), array( 'id' => $bolID ) );
										break;
									case "finalizado_$bolID":
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_pedido' => self::STATUS_PEDIDO_FINALIZADO ), array( 'id' => $bolID ) );
										break;
									case "excluir_$bolID":
										// @todo usar modal para confirmar exclusão
										$wpdb->delete( self::TRAJ_BOLETOS_TABLE, array( 'id' => $bolID ) );
										break;
									case "ver_$bolID": // talvez aqui seja melhor usar uma âncora alterando as query vars
										$key = $wpdb->get_var( "SELECT key_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id = " . $bolID );
										$link = self::_helper_boleto_link( $key );
										echo "<meta http-equiv='refresh' content='0;url=$link' />";
										exit;
										break;
									case "segunda-via_$bolID":
										// @todo chamar página boleto pré-poluando os campos 
										break;
									case "enviar_$bolID": 
										// A opção “enviar para cliente” envia um e-mail ao cliente, contendo um link para “ver-boleto” usando a key como query string.$ids = implode(", ", $boletoID);
										$boleto = $wpdb->get_row( "SELECT id, email, nome, key_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id = " . $bolID, OBJECT );
										// @todo preparar texto de subject e content 
										$content = self::_helper_boleto_link( $boleto->key_boleto );
										self::_send_mail($boleto->email, "Boleto", $content);
										break;
									case "pedido_$bolID": // talvez aqui seja melhor usar uma âncora alterando as query vars
										break;
									default:
										break;
								}
								break;
							}
						}
					}
					
					// tratando ação bulk-action
					if ( isset( $_POST['boleto'] ) ) {
						
						$boletoID = preg_replace('/[^-0-9]/', '', $_POST['boleto'] );
						
						switch ( $_POST['bulk-action'] ) {
							case "pago":
								foreach ( $boletoID as $id ) {
									$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_PAGO ), array( 'id' => $id ) );
								}
								// @todo verificar se alterações no banco obtiveram mesmo sucesso para definir mensagem de resultado... aqui e em todas as $msg
								$msg["bulk_action"] = '<div class="alert fade in"><button type="button" class="close" data-dismiss="alert">×</button>Os boletos selecionados foram marcados como pagos!</div>';
								break;
							case "nao-pago":
								// checa se boleto venceu
								foreach ( $boletoID as $id ) {
									$statusBoleto = $wpdb->get_var( $wpdb->prepare( "SELECT status_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id = " . $id ) );
									if( $statusBoleto != self::STATUS_BOLETO_VENCIDO ) {
										$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_EM_ABERTO ), array( 'id' => $id ) );
									}
								}
								break;
							case "cancelar":
								foreach ( $boletoID as $id ) {
									$wpdb->update( self::TRAJ_BOLETOS_TABLE, array( 'status_boleto' => self::STATUS_BOLETO_CANCELADO ), array( 'id' => $id ) );
								}
								break;
							case "excluir":
								// @todo implementar modal na selectbox caso excluir
								foreach ( $boletoID as $id ) {
									$wpdb->delete( self::TRAJ_BOLETOS_TABLE, array( 'id' => $id ) );
								}
								break;
							case "enviar":
								// A opção “enviar para cliente” envia um e-mail ao cliente, contendo um link para “ver-boleto” usando a key como query string.
								$ids = implode(", ", $boletoID);
								$boletos = $wpdb->get_results( "SELECT id, email, nome, key_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id IN (" . $ids . ")", OBJECT_K );
								
								foreach ( $boletos as $bol ) {
									// @todo preparar texto de subject e content 
									$content = self::_helper_boleto_link( $bol->key_boleto );
									self::_send_mail($bol->email, "Boleto", $content);
								}
								
								break;
							default:
								break;
						}
					}
					
					// preparando resumo de valores dos boletos
					$somaTotal = $wpdb->get_results( "SELECT status_boleto, sum( valor ) as total FROM " . self::TRAJ_BOLETOS_TABLE . " GROUP BY status_boleto", OBJECT_K );
					if ( array_key_exists(self::STATUS_BOLETO_EM_ABERTO, $somaTotal) )	
						$totalNaoPago = $somaTotal[self::STATUS_BOLETO_EM_ABERTO]->total;
					else
						$totalNaoPago = 0;
					if ( array_key_exists(self::STATUS_BOLETO_PAGO, $somaTotal) )
						$totalPago = $somaTotal[self::STATUS_BOLETO_PAGO]->total;
					else
						$totalPago = 0;
					if ( array_key_exists(self::STATUS_BOLETO_VENCIDO, $somaTotal) )
						$totalVencido = $somaTotal[self::STATUS_BOLETO_VENCIDO]->total;
					else
						$totalVencido = 0;
						
					
					// preparando paginação
					if ( !isset($_GET['order_by']) ) {
						$order = 'data_vencimento';
					} else {
						$order = get_query_var('order_by');
					}
					if ( !isset($_GET['sort']) ) {
						$sort = 'desc';
					} else {
						$sort = get_query_var('sort');
					}
					if ( !isset($_GET['limit']) ) {
						$limit = 20;
					} else {
						$limit = get_query_var('limit');
					}
					if ( !isset($_GET['offset']) || $_GET['offset'] < 0 ) {
						$offset = 0;
					} else {
						$offset = get_query_var('offset');
					}
					if ( !isset($_GET['cpf']) ) {
						$boletos = $wpdb->get_results( "SELECT * FROM " . self::TRAJ_BOLETOS_TABLE . " order by $order $sort LIMIT $limit OFFSET $offset", OBJECT_K );
					} else {
						$cpf = get_query_var('cpf');
						$boletos = $wpdb->get_results( "SELECT * FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE cpf=$cpf ORDER BY $order $sort LIMIT $limit OFFSET $offset", OBJECT_K );
					}
					
			?>
			
					<div class="clear"></div>
					
					<div class="alert alert-info alert-big center" id="resumo-boletos">
						<div class="row-fluid">
  							<div class="span4">Pago: <span>R$<?php echo number_format( $totalPago, 2, ',', '.' ); ?></span></div>
  							<div class="span4">Não pago: <span>R$<?php echo number_format( $totalNaoPago, 2, ',', '.' ); ?></span></div>
  							<div class="span4">Vencido: <span>R$<?php echo number_format( $totalVencido, 2, ',', '.' ); ?></span></div>
						</div>
					</div>
					
					<div class="alert alert-info alert-big center" id="quick-change">
						<form class="form-inline" method="POST" enctype="multipart/form-data" action="">
							<label for="nosso_numero">Nosso Número:</label>
							<input type="text" name="nosso_numero" class="input-small" id="nosso-numero" />
							<input type="submit" name="submit_quickchange" value="Marcar como pago" class="btn" id="button-marcar-pago" />
							<input type="submit" name="submit_quickchange" value="Marcar como não pago" class="btn" id="button-marcar-naopago" />
							<input type="submit" name="submit_quickchange" value="Cancelar" class="btn" id="button-cancelar" />
							<input type="button" value="Excluir" class="btn" id="button-excluir" />	
							<div class="modal hide fade in" id="excluirBoleto" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
							  <div class="modal-header">
							    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
							    <h3 id="excluir-boleto-ModalLabel">Confirmar exclusão de boleto</h3>
							  </div>
							  <div class="modal-body">
							    <p>Tem certeza que deseja prosseguir? Essa ação não pode ser revertida.</p>
							  </div>
							  <div class="modal-footer">
							    <input type="submit" name="submit_quickchange" value="Excluir" class="btn" />
							    <button class="btn btn-primary close-modal" data-dismiss="modal" aria-hidden="true">Cancelar</button>
							  </div>
							</div>				
						</form>
						<?php echo $msg["quick_change"]; ?>
						<span id="msg-quick-change"></span>
					</div>
					
					<form method="POST" enctype="multipart/form-data" action="" class="form-inline" id="boletos">
						<table class="table table-striped table-custom-padding" id="bol-table" >
							<thead>
								<tr class="bol-tpagination">
									<th colspan="9" >
										<div class="row-fluid table-header">
											<div class="span4 center text">Mostrando boletos <?php echo $offset+1; ?> a <?php echo sizeof($boletos) + $offset; ?> de <?php echo $totalBoletos; ?></div>
											<div class="span4 center pagination-custom">
												<ul>
													<li><a href="?modo=todos&offset=0&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><button class="btn btn-small btn-primary" type="button"><<</button></a></li>
													<li><a href="?modo=todos&offset=<?php if ($offset-$limit-1 < 0) echo 0; else echo $offset-$limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><button class="btn btn-small btn-primary" type="button"><</button></a></li>
													<li><input type="text" class="input-mini" id="ir-para-pagina" value="<?php echo floor($offset / $limit) + 1; ?>" /></li>
													<li><a href="?modo=todos&offset=<?php if ($offset+$limit+1 > $totalBoletos) echo $offset; else echo $offset+$limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><button class="btn btn-small btn-primary" type="button">></button></a></li>
													<li><a href="?modo=todos&offset=<?php if( $totalBoletos % $limit == 0 ) echo $totalBoletos - $limit; else echo $totalBoletos - $totalBoletos % $limit; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>"><button class="btn btn-small btn-primary" type="button">>></button></a></li>
												</ul>
											</div>
											<div class="span4 center form-inline">
												<label for="boletos-por-pagina">Boletos por página:</label>
												<select name="limit" class="input-mini" id="boletos-por-pagina">
													<option class="active"><?php echo $limit; // @todo popular dinamicamente essa select com a quantidade real de páginas até no máximo 20 ?></option>
													<option value="1">1</option>
													<option value="5">5</option>
													<option value="10">10</option>
													<option value="15">15</option>
													<option value="20">20</option>
												</select>
											</div>
										</div> 
									</th>
								</tr>
								<tr class="bol-thead">
									<th class="col-head"></th>
									<th class="col-head <?php if($order=="nosso_numero" && $sort=="asc") echo "sort-asc"; elseif($order=="nosso_numero" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "nosso_numero"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Nosso Número</a>
									</th>
									<th class="col-head <?php if($order=="data_criacao" && $sort=="asc") echo "sort-asc"; elseif($order=="data_criacao" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "data_criacao"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Dt. Emissão</a>
									</th>
									<th class="col-head <?php if($order=="data_vencimento" && $sort=="asc") echo "sort-asc"; elseif($order=="data_vencimento" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "data_vencimento"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Dt. Vencimento</a>
									</th>
									<th class="col-head <?php if($order=="nome" && $sort=="asc") echo "sort-asc"; elseif($order=="nome" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "nome"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Cliente</a>
									</th>
									<th class="col-head <?php if($order=="servico" && $sort=="asc") echo "sort-asc"; elseif($order=="servico" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "serviço"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Serviço</a>
									</th>
									<th class="col-head <?php if($order=="status_boleto" && $sort=="asc") echo "sort-asc"; elseif($order=="status_boleto" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_boleto"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Status do Boleto</a>
									</th>
									<th class="col-head <?php if($order=="status_pedido" && $sort=="asc") echo "sort-asc"; elseif($order=="status_pedido" && $sort=="desc") echo "sort-desc"; else echo "sort"; ?>">
										<a href="?modo=todos&offset=<?php echo $offset; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo "status_pedido"; ?>&sort=<?php if ($sort == "desc") echo "asc"; else echo "desc"; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>">Status do Pedido</a>
									</th>
									<th class="col-head col-opcoes">
										<span>Opções</span>
									</th>
								</tr>
							</thead>
							<tbody>
				
								
							<?php foreach ( $boletos as $bol ) { ?>
								<tr class='bol-$bol->id'>
									<td class="check bol-check"><input type='checkbox' name='boleto[]' value='<?php echo $bol->id ?>' /></td>
									<td class="data bol-nossonumero"><?php echo $bol->nosso_numero; ?></td>
									<td class="data bol-dtemissao"><?php echo substr_replace( date_to_br( $bol->data_criacao ), "", 10 ); ?></td>			
									<td class="data bol-dtvencimento"><?php echo substr_replace( date_to_br( $bol->data_vencimento ), "", 10 ); ?></td>
									<td class="data bol-cliente"><?php echo $bol->nome; ?></td>
									<td class="data bol-servico"><?php echo $bol->descricao; ?></td>
									<td class="data bol-statusbol">
										<?php 
											switch ( $bol->status_boleto ) {
												case self::STATUS_BOLETO_EM_ABERTO:
													echo "Aberto"; 
													break;
												case self::STATUS_BOLETO_CANCELADO:
													echo "Cancelado";
													break;
												case self::STATUS_BOLETO_PAGO:
													echo "Pago";
													break;
												case self::STATUS_BOLETO_VENCIDO:
													echo "Vencido";
													break;
												default:
													break;
											}
										?>
									</td>
									<td class="data bol-statuspedido">
										<?php
											switch ( $bol->status_pedido ) {
												case self::STATUS_PEDIDO_NAO_INICIADO:
													echo "Aguardando";
													break;
												case self::STATUS_PEDIDO_EM_EXECUCAO:
													echo "Em execução";
													break;
												case self::STATUS_PEDIDO_FINALIZADO:
													echo "Finalizado";
													break;
												default:
													break;
											}
										?>
									</td>
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
						
						<div class="form-actions center">
							<label for="bulk-action">Com marcados:</label>
							<select name="bulk-action" id="bulk-action" onchange="this.form.submit()">
								<option value="selecione">Selecione</option>
								<option value="pago">Marcar como pago</option>
								<option value="nao-pago">Marcar como aberto</option>
								<option value="cancelar">Cancelar</option>
								<option value="excluir">Excluir</option>
								<option value="enviar">Enviar para cliente</option>
							</select>
						</div>
						
					</form>
					
			<?php
					
					break;
			}
			
			?>
			
			<div class="modal hide fade in" id="excluir-boleto-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
			  <div class="modal-header">
			    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			    <h3 id="excluir-boleto-ModalLabel">Confirmar exclusão de boleto</h3>
			  </div>
			  <div class="modal-body">
			    <p>Tem certeza que deseja prosseguir? Essa ação não pode ser revertida.</p>
			  </div>
			  <div class="modal-footer">
			    <input type="submit" id="excluir-boleto" value="excluir" class="btn" />
			    <button class="btn btn-primary close-modal" data-dismiss="modal" aria-hidden="true">Cancelar</button>
			  </div>
			</div>
			
			<div class="modal hide fade in" id="generic-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
			  
			</div>
			
			<script type="text/javascript">

				jQuery(document).ready(function() {
					
					jQuery("#ir-para-pagina").keyup(function(e) {
						if(e.keyCode == 13) {
							var intRegex = /^\d+$/;
							var pagina = jQuery(this).val();
							var offset = <?php echo $limit; ?> * (pagina - 1);
							if (pagina < 1 || pagina > <?php echo ceil( $totalBoletos / $limit ); ?>|| !intRegex.test(pagina) ) {
								offset = <?php echo $offset; ?>
							}
							window.location.href = '<?php echo get_permalink() . "?modo=" . get_query_var('modo'); ?>&offset=' + offset + '&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>';
						}
					});
					
					jQuery("#boletos-por-pagina").change(function() {
						window.location.href = '<?php echo get_permalink() . "?modo=" . get_query_var('modo'); ?>&offset=0&limit=' + jQuery(this).val() + '&order_by=<?php echo $order; ?>&sort=<?php echo $sort; if (get_query_var("cpf")) echo "&cpf=" . get_query_var("cpf"); ?>';
					});

					jQuery(".cliente-opcao").change(function() {
						var option = jQuery(this).val().split("_");
						var cpf = option[1];
						switch (option[0]) {
							case "boletos":
								window.location.href = '<?php echo get_permalink() . "?modo=todos&offset=0"; ?>&limit=<?php echo $limit; ?>&order_by=<?php echo $order; ?>&sort=<?php echo $sort; ?>&cpf=' + cpf;
								break;
							case "dados":
								// @todo: gerar pop-up com dados do cliente e sumário de boletos/pedidos
								jQuery.ajax({
									url: "<?php echo plugins_url("popups.php",__FILE__); ?>?popup=cliente&cpf=" + cpf,
								 	dataType: "html"
								}).done(function(data){
									jQuery("#generic-modalLabel").html("Detalhes do cliente");
									jQuery("#generic-modal").html(data);
									jQuery("#generic-modal").modal("show");
								});

								break;
						}
						
					});
					
					jQuery(".close-modal").click(function(){
						jQuery(".opcao").val("Selecione");
					});

					jQuery(".bol-opcao").change(function() {
						var option = jQuery(this).val().split("_");
						var id = option[1];
						switch (option[0]) {
							case "excluir":
								// usar modal para confirmar exclusão do boleto
								jQuery("#excluir-boleto-modal").modal("show");
								break;
							case "ver":
								// chamar ver-boleto
								// $key = $wpdb->get_var( "SELECT key_boleto FROM " . self::TRAJ_BOLETOS_TABLE . " WHERE id = " . $bolID );
								//	$link = self::_helper_boleto_link( $key );
								//	echo "<meta http-equiv='refresh' content='0;url=$link' />";
								//	exit;
								break;
							case "segunda-via":
								// chama “boleto”, para que seja criado um NOVO boleto 
								// (neste caso, os campos são pré-populados com os dados do cliente, 
								// valor, etc., 
								// MAS aplicando uma nova data de vencimento a partir da data atual de emissão). 
								break;
							case "pedido":
								// abre um pop-up com os detalhes do pedido, 
								// inclusive descrição digitada pelo cliente e 
								// link para download do(s) arquivo(s) enviado(s)
								jQuery.ajax({
									url: "<?php echo plugins_url("popups.php",__FILE__); ?>?popup=pedido&bol-id=" + id,
								 	dataType: "html"
								}).done(function(data){
									jQuery("#generic-modalLabel").html("Detalhes do pedido");
									jQuery("#generic-modal > .modal-body").html(data);
									jQuery("#generic-modal").modal("show");
								});
								
								break;
							default:
								jQuery("#boletos").submit();
								break;
						}
					});

					jQuery("#button-excluir").click(function() {
						var nossoNumero = jQuery("#nosso-numero").val();
						var intRegex = /^\d+$/;
						if ( !intRegex.test(nossoNumero) ) {
							jQuery("#msg-quick-change").html('<div class="alert alert-error fade in alert-custom-margin"><button type="button" class="close" data-dismiss="alert">×</button>Por favor, preencha corretamente o campo "nosso número". Ele deve conter apenas números.</div>');
						} else {
							jQuery("#excluirBoleto").modal("show");
						}
					});

					// @todo estudando ajax/jquery
					jQuery("#excluir-boleto").click();
					
				});
				
			</script>
			
			<?php
			
		}
	}
	
	###############################################################################################################

	# FUNCTION: pagina_segunda_via
	# DESCRIPTION: renderiza a página Segunda Via de Boleto, shortcode trajettoria-segunda-via
	public static function pagina_segunda_via()
	{
		echo "Página Segunda Via de Boletos do plugin";
	}
	
	###############################################################################################################

	# FUNCTION: query_vars
	# DESCRIPTION: adiciona query vars usadas neste plugin.
	public static function query_vars($vars)
	{
		$vars[] = 'prod_id';
		$vars[] = 'modo';
		$vars[] = 'offset';
		$vars[] = 'limit';
		$vars[] = 'order_by';
		$vars[] = 'sort';
		$vars[] = 'cpf';
		$vars[] = 'key';
		$vars[] = 'nosso_numero';
		$vars[] = 'trajettoria_page';
		return $vars;
	}	

	###############################################################################################################

	# FUNCTION: _list_files
	# DESCRIPTION: lista os nomes dos arquivos indicados para upload num html com quebras de linha <br>

	private static function _list_files() 
	{
		$files = $_FILES['arquivos'];
		if ( is_array( $files ) ) {
			
			$html = array();
			foreach ( $files['name'] as $value )
			{	
				$html[] = $value;
			}
			$html = implode("<br />", $html);
			return $html;
		}
		else return "Nenhum arquivo.";
	}
	
	###############################################################################################################

	# FUNCTION: _process_uploads
	# DESCRIPTION: move os arquivos armazenados na pasta temporária do servidor após submissão de um form
	# e retorna array com nomes de arquivos finais na pasta de destino

	private static function _process_uploads( $allowedTypes, $maxFilesize ) 
	{
		$files = $_FILES['arquivos'];
		$processedFiles = NULL;
		
		if ( is_array( $files ) ) {
			
			foreach ( $files['name'] as $key => $value ) {
				
				// work only with successfully uploaded files
				if ( $files['error'][$key] == 0 ) {
					
					// full actual file name (it'll be sanitized later)
					$filename = $files['name'][$key];
					
					// get the file extension
					$filetype = wp_check_filetype( $filename );
					
					// check if file is allowed by extension. If it's not allowed, echo the error and move to next file			
					if ( !in_array( $filetype['ext'], $allowedTypes ) ) {
						echo "Falha no envio do arquivo '$filename'. Verifique se a extensao corresponde a uma das permitidas.";
						continue;
					}
					// get the file size
					$filesize = $files['size'][$key];
					
					// check if file size exceeds $maxFilesize. If it does, echo the error end move to next file
					if ( $filesize > $maxFilesize ) {
						echo "Falha no envio do arquivo '$filename'. O tamanho ultrapassou o limite permitido.";
						continue;
					}		
					// temporary file name assigned by the server
					$filetmp = $files['tmp_name'][$key];
					
					// drop the extension, sanitize file name and remove accents, we need just the clean title
					$filetitle = remove_accents( sanitize_file_name( basename( $filename, '.'.$filetype['ext'] ) ) );
					
					// construct fresh new sanitized file name
					$filename = $filetitle.'.'.$filetype['ext'];
					
					// all processed files will be found here
					$upload_dir = plugin_dir_path( __FILE__ ).'uploads';
					
					// resolve existing file names by adding '_$i', where $i is the number of times it has just repeated 
					$i = 1;
					while ( file_exists( $upload_dir.'/'.$filename ) )
					{
						$filename = $filetitle.'_'.$i.'.'.$filetype['ext'];
						$i++;
					}
					
					// final file path
					$filedest = $upload_dir.'/'.$filename;
					
					// move the file from temp dir to final file path
					if ( !copy( $filetmp, $filedest ) )
					{
						echo "O arquivo '$filename' não pôde ser copiado para a pasta destino.";
					}
					
					$processedFiles[] = $filename;
				}
			}
		}
		return $processedFiles;
	}
	
	###############################################################################################################

	# FUNCTION: _get_setting
	# DESCRIPTION: obtém o valor da propriedade 'prop', a partir da tabela wp_options
	private static function _get_setting($prop)
	{
		return get_option(WP_Plugin_Options::PREFIX . '_' . $prop);
	}
	
	###############################################################################################################

	# FUNCTION: _send_mail
	# DESCRIPTION: envia um e-mail usando o servidor SMTP informado nas configurações globais do plugin
	private static function _send_mail( $to, $subject, $content )
	{

		$host = self::_get_setting( "email_host" );
		$auth = self::_get_setting( "email_auth" );
		$secure = self::_get_setting( "email_secure" );
		$port = self::_get_setting( "email_porta" );
		$username = self::_get_setting( "email_username" );
		$password = self::_get_setting( "email_senha" );
		$from = self::_get_setting( "email" );
		$name = self::_get_setting( "email_from_alias" );
		
		return SendMail($host, $auth, $secure, $port, $username, $password, $from, $name, $to, $subject, $content);
	}

	###############################################################################################################

	# FUNCTION: _helper_boleto_link
	# DESCRIPTION: cria o URL para a página de renderização do boleto. Fornecer 'key' OU ('nosso_numero' e 'cpf')
	private static function _helper_boleto_link($key=NULL, $nosso_numero=NULL, $cpf=NULL)
	{
		$key = urlencode($key);
		$nosso_numero = urlencode($nosso_numero);
		$cpf = urlencode($cpf);
		
		return get_site_url() . "/ver-boleto/?key=$key&nosso_numero=$nosso_numero&cpf=$cpf";
	}
	
	###############################################################################################################

	# FUNCTION: _get_due_date
	# DESCRIPTION: obtém a data de vencimento a partir de "start" (YYYY-MM-DD), somando "days"
	# Data retornada no formato YYYY-MM-DD
	private static function _get_due_date($start, $days)
	{
		$start = strtotime($start);
		return date('Y-m-d', strtotime('+' . $days . ' day', $start));
	}
	

	###############################################################################################################
	
	# FUNCTION: _get_servico
	# DESCRIPTION: retorna um array com os detalhes do serviço com post_id igual a 'prod_id'
	# Este array possui as chaves: id, title, content, excerpt, usar_boleto, valor, taxa, permitir_upload, 
	# label_descricao, dias_vencimento, formatos_arquivo, data_vencimento
	private static function _get_servico($prod_id)
	{
		$p = get_post($prod_id);
		if($p)
		{
			if($p->post_type != 'servico') return FALSE;

			$r = array();
			$r['id'] = $prod_id;
			$r['title'] = $p->post_title;
			$r['content'] = $p->post_content;
			$r['excerpt'] = $p->post_excerpt;
			$r['usar_boleto'] = checkbox_to_bool(get_post_meta($prod_id, 'boleto-usar', TRUE));
			$r['valor'] = get_post_meta($prod_id, 'boleto-valor', TRUE);
			$r['taxa'] = get_post_meta($prod_id, 'boleto-taxa', TRUE);
			$r['permitir_upload'] = checkbox_to_bool(get_post_meta($prod_id, 'boleto-permitir-upload', TRUE));
			$r['label_descricao'] = get_post_meta($prod_id, 'boleto-label-descricao', TRUE);
			$r['dias_vencimento'] = get_post_meta($prod_id, 'boleto-dias-vencimento', TRUE);
			
			
			if(!$r['usar_boleto']) return FALSE;
			
			if(empty($r['taxa'])) $r['taxa'] = self::_get_setting('taxa');
			if(empty($r['label_descricao'])) $r['label_descricao'] = self::_get_setting('label_descricao');
			if(empty($r['dias_vencimento'])) $r['dias_vencimento'] = self::_get_setting('dias_vencimento');
			$agora = NowDatetime();
			$r['data_vencimento'] = self::_get_due_date($agora, $r['dias_vencimento']);
			
			$r['formatos_arquivo'] = self::_get_setting('formatos_arquivo');
			
			return $r;
		}
		else
		{
			return FALSE;
		}
	}
} // end class "TrajettoriaBoletos"

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
register_activation_hook( __FILE__, array( 'TrajettoriaBoletos', 'install' ) );
register_deactivation_hook( __FILE__, array( 'TrajettoriaBoletos', 'uninstall' ) );
$__traj_boletos = new TrajettoriaBoletos();
?>
