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