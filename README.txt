v0.2
20/08/12 - Francisco

#descrição
Estruturação da classe do plugin e seus recursos; promeiro formulário criado (pedido).

#changelog
Implementado custom post Serviço;
Implementado backend configurações globais;
Adaptado esqueleto original da classe para utilização das classes que servem de base para a construção de plugins;
Criado modelo de formulário na página "Pedido", usando parcialmente o bootstrap para estilização, masked input, css próprio em style-boletos.css;
Criado o modelo de dados no workbench e criada a respectiva tabela no banco wp_boletos1;
Programado mecanismo de url rewrite para a página "ver boleto" e "servicos", usando API do WP;
Acrescentada função get_ip em util.php;
Inclusos arquivos básicos para geração de boleto do Santander. Falta adaptação do código;
Escolhido template a ser usado em definitivo no site
>>> NOVOS ESTILOS DE CSS DEVEM SER COLOCADOS EM style-boletos.css <<<;

#known issues
vários ainda

#todo
vide linhas "//TODO" no source code
vide especificações (foram modificadas!)


=====================================================================================
v0.1 
12/08/12 - Francisco

#descrição
1st commit, com estrutura básica de pastas do WordPress 3.4.1 pt-BR.
Deve ser clonado para C:/AppServ/www (antes de clonar, configurar o local do clone no github for Windows).
Usuários criados: "admin", "trajettoria" e "usuario", "trajettoria".
Modificada estrutura de permalinks para "/%postname%/" e outras configurações gerais.
Deve ser criado manualmente um banco MySQL "wp_boletos1" em localhost, usuário "root", senha "krieger". O dump .sql localizado na pasta /wp-content/plugins/trajettoria-boletos/DEVELOPMENT deve ser importado ao banco recém criado.
Procurar usar este modelo de README.txt ao longo do desenvolvimento.
Mudar a minor version a cada commit no github.
>>>Antes de cada commit, criar um dump do banco de dados e armazenar em /DEVELOPMENT<<<
>>>Após fazer o download de uma nova versão a partir do github, importar o dump mais recente ao banco de dados, antes de retomar o desenvolvimento<<<
Explorar o arquivo /wp-content/plugins/trajettoria-boletos/includes/util.php antes de iniciar o desenvolvimento para conhecer algumas funções auxiliares que já foram disponibilizadas.


#changelog

#known issues

#todo
Vide backlog para orientações sobre o desenvolvimento.