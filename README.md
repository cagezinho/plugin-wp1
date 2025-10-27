# Ferramentas Upload

Plugin WordPress para gerenciamento em massa de uploads e atualizações, incluindo textos alternativos de imagens, meta tags do Yoast SEO, exportação de posts e categorias, e movimentação de posts para a lixeira.

## Funcionalidades

- Atualização em massa de textos alternativos (alt text) de imagens
- Atualização em massa de meta tags do Yoast SEO
- Exportação de posts e categorias
- Movimentação em massa de posts para a lixeira

## Requisitos

- WordPress 5.0 ou superior
- PHP 7.2 ou superior
- Plugin Yoast SEO (para funcionalidades relacionadas ao SEO)

## Instalação

1. Faça o upload dos arquivos do plugin para a pasta `/wp-content/plugins/ferramentas-upload`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse as funcionalidades do plugin através do menu 'Ferramentas Upload' no painel administrativo

## Como Usar

### Atualização de Textos Alternativos
1. Prepare um arquivo CSV com duas colunas: URL da imagem e texto alternativo desejado
2. Acesse a aba 'Alt Text' no menu do plugin
3. Faça upload do arquivo CSV
4. O plugin atualizará automaticamente os textos alternativos das imagens

### Atualização de Meta Tags Yoast SEO
1. Prepare um arquivo CSV com as URLs dos posts e as meta tags desejadas
2. Acesse a aba 'SEO' no menu do plugin
3. Faça upload do arquivo CSV
4. O plugin atualizará as meta tags do Yoast SEO

### Exportação de Posts
1. Acesse a aba 'Exportar' no menu do plugin
2. Selecione os critérios desejados
3. Clique em 'Exportar' para baixar o arquivo CSV

### Movimentação para Lixeira
1. Prepare um arquivo CSV com as URLs dos posts a serem movidos
2. Acesse a aba 'Lixeira' no menu do plugin
3. Faça upload do arquivo CSV
4. O plugin moverá os posts selecionados para a lixeira

## Modelos de CSV

O plugin inclui modelos de arquivos CSV para cada funcionalidade. Você pode encontrá-los na pasta do plugin:
- `modelo-exclusao-post.csv` - Para movimentação de posts para lixeira
- `modelo-para-recat.csv` - Para recategorização de posts
- `modelo-serps-yoast.csv` - Para atualização de meta tags do Yoast SEO

## Suporte

Para suporte, por favor abra uma issue no repositório do GitHub ou entre em contato através do site oficial do plugin.

## Licença

Este plugin está licenciado sob a GPL v2 ou posterior - veja o arquivo [LICENSE](LICENSE) para detalhes.

## Autor

Desenvolvido por [Nicolas Cage](https://github.com/cagezinho)