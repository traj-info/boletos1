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
			require_once("get_boleto.php?key=$key&nosso_numero=$nosso_numero&cpf=$cpf");
            exit();
		}
    }
	
	###############################################################################################################

	# FUNCTION: pagina_pedido
	# DESCRIPTION: renderiza a página Pedido, shortcode trajettoria-pedido
	public static function pagina_pedido()
	{
		$prod_id = get_query_var('prod_id');

		

		?>
		<div class="alignright">
			<ul class="nav nav-pills">
			  <li class="active">ETAPA 1: Cadastro</a></li>
			  <li>ETAPA 2: Revisão</li>
			  <li>ETAPA 3: Boleto</li>
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
			echo "<input type='hidden' name='hidden_step' id='hidden_step' value='2' />";
			
			echo "<fieldset>";
			
			?>
	
			<div class="control-group">
			<label class="control-label" for="nome">Nome:</label>
			<div class="controls">
				<input type="text" class="input-xlarge" id="nome" name="nome">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="cpf">CPF:</label>
			<div class="controls">
				<input type="text" class="input-medium" id="cpf" name="cpf">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="instituicao">Instituição:</label>
			<div class="controls">
				<input type="text" class="input-xlarge" id="instituicao" name="instituicao">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="email">E-mail:</label>
			<div class="controls">
				<input type="text" class="input-xlarge" id="email" name="email">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="telefone">Telefone:</label>
			<div class="controls">
				<input type="text" class="input-medium" id="telefone" name="telefone">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="celular">Celular:</label>
			<div class="controls">
				<input type="text" class="input-medium" id="celular" name="celular">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="endereco">Endereço:</label>
			<div class="controls">
				<textarea name="endereco" id="endereco" class="input-xlarge" rows="3" cols="150"></textarea>
				<p class="help-block">Exemplo: Av. Paulista, 2200 - cj. 161</p>
			</div>
			
			<div class="control-group">
			<label class="control-label" for="cep">CEP:</label>
			<div class="controls">
				<input type="text" class="input-small" id="cep" name="cep">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="cidade">Cidade:</label>
			<div class="controls">
				<input type="text" class="input-medium" id="cidade" name="cidade">
			</div>
			
			<div class="control-group">
			<label class="control-label" for="uf">UF:</label>
			<div class="controls">
				<select name="uf" id="uf" class="input-medium">
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
			
			
			<div class="control-group">
			<label class="control-label" for="descricao"><?php echo $s['label_descricao']; ?>:</label>
			<div class="controls">
				<textarea name="descricao" id="descricao" class="input-xxlarge" rows="5" cols="150"></textarea>
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
			<button class='btn btn-success' id='novo-arquivo' onclick='return false;'><i class="icon-plus icon-white"></i> adicionar outro arquivo...</button>					
			</div>
			
			<script type="text/javascript">
			jQuery(document).ready(function(){
			
				// File upload inputs
				jQuery('#novo-arquivo').click(function(){
					cont_arq = jQuery('.linha-arquivo').length;
					cont_arq++;
					if(cont_arq < 6) {
						jQuery("<div class='linha-arquivo'><input class='input-file' id='arquivos[]' name='arquivos[]' type='file'><button class='btn btn-danger remover-arquivo' onclick='return false;'><i class='icon-trash icon-white'></i> remover</button></div>").appendTo(jQuery('#arquivos-holder'));
						
						jQuery('.remover-arquivo').click(function(){
							jQuery(this).parent('.linha-arquivo').remove();
							cont_arq = jQuery('.linha-arquivo').length;
							if(cont_arq < 6)
							{
								jQuery('#novo-arquivo').show();
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
					rules: {
						
					},
					messages: {
					
					}
				});

				// Input masks
				jQuery('#cpf').mask('999.999.999-99');
				jQuery('#telefone').mask('(99) 99999999? ********************');
				jQuery('#celular').mask('(99) 99999999?9');
				jQuery('#cep').mask('99999-999');
				
			});
			
			function redireciona(url)
			{
				window.location.href = url;
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
			

	}
	
	###############################################################################################################

	# FUNCTION: pagina_painel_boletos
	# DESCRIPTION: renderiza a página Painel de Boletos, shortcode trajettoria-painel-boletos
	public static function pagina_painel_boletos()
	{
		echo "Página Painel de Boletos do plugin";
		
		// abaixo: exemplos
		echo self::_helper_boleto_link('JNDHF8Y4GRUFHDJF', '0087998', '35941385854'); 
		
		$prod_id = get_query_var('prod_id');
		
		echo "<br><br>prod_id = " . $prod_id;
		echo "<br><br>valor da propriedade 'label': " . self::_get_setting('label_descricao'); 		
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

	# FUNCTION: _get_setting
	# DESCRIPTION: obtém o valor da propriedade 'prop', a partir da tabela wp_options
	private static function _get_setting($prop)
	{
		return get_option(WP_Plugin_Options::PREFIX . '_' . $prop);
	}
	
	###############################################################################################################

	# FUNCTION: _send_mail
	# DESCRIPTION: envia um e-mail usando o servidor SMTP informado nas configurações globais do plugin
	private static function _send_mail($to, $subject, $content)
	{
		
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
	
	# FUNCTION: _get_servico
	# DESCRIPTION: retorna um array com os detalhes do serviço com post_id igual a 'prod_id'
	# Este array possui as chaves: id, title, content, excerpt, usar_boleto, valor, taxa, permitir_upload, 
	# label_descricao, dias_vencimento, formatos_arquivo
	private static function _get_servico($prod_id)
	{
		$p = get_post($prod_id);
		if($p)
		{
		
			// TODO: checar se é um "serviço"
			
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
			
			//TODO: recuperar valores globais se estiver em branco ou com erro algum dos campos acima
			//TODO: recuperar os formatos de arquivo
			
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
