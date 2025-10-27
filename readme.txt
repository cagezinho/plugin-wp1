=== Ferramentas Upload ===
Contributors: cagezinho, adryanno, augusto
Donate link: https://github.com/cagezinho/plugin-wp1
Tags: upload, alt text, seo, yoast, bulk actions, export, categories, posts
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin para gerenciar uploads e atualizações em massa, incluindo textos alternativos de imagens, meta tags do Yoast SEO, exportação de posts e categorias, recategorização e exclusão segura.

== Description ==

O Ferramentas Upload é um plugin WordPress completo que oferece funcionalidades avançadas para gerenciamento em massa de diversos aspectos do seu site:

* **Exportação Completa de Posts** - Exporta todos os posts com conteúdo HTML completo
* **Recategorização em Massa** - Altera categorias de posts através de CSV
* **Exclusão Segura** - Move posts para lixeira em massa (reversível)
* **Atualização de Textos Alternativos** - Alt text de imagens em massa
* **Atualização de Meta Tags SEO** - Títulos e descrições do Yoast SEO

= Principais Funcionalidades =

* **Exportação de Posts e Categorias**
  * Exporta todos os posts publicados
  * Inclui conteúdo HTML completo
  * Gera excerpts automáticos
  * Formato CSV compatível com Excel/Google Sheets

* **Recategorização em Massa**
  * Altera categorias via CSV
  * Cria categorias automaticamente
  * Validação de URLs e posts
  * Relatório detalhado

* **Exclusão Segura (Lixeira)**
  * Move posts para lixeira em massa
  * Permite restauração posterior
  * Ignora páginas e anexos
  * Operação reversível

* **Textos Alternativos de Imagens**
  * Atualiza alt text via CSV
  * Atualiza automaticamente em posts
  * Suporte a encoding UTF-8
  * Validação de URLs

* **Meta Tags SEO (Yoast)**
  * Atualiza títulos e descrições
  * Requer Yoast SEO ativo
  * Validação completa
  * Suporte a caracteres especiais

= Recursos Técnicos =

* Tratamento robusto de erros
* Suporte a encoding UTF-8
* Otimizações de performance
* Validação de segurança
* Limites automáticos de memória e tempo
* Interface administrativa intuitiva

== Installation ==

1. Baixe o plugin do [GitHub](https://github.com/cagezinho/plugin-wp1)
2. Faça o upload dos arquivos do plugin para a pasta `/wp-content/plugins/ferramentas-upload`
3. Ative o plugin através do menu 'Plugins' no WordPress
4. Acesse as funcionalidades do plugin através do menu 'Ferramentas Upload' no painel administrativo
5. **IMPORTANTE:** Faça backup do banco de dados antes de executar operações em massa

== Frequently Asked Questions ==

= O plugin é compatível com o Yoast SEO? =

Sim, o plugin é totalmente compatível com o Yoast SEO para atualização de meta tags. O Yoast SEO deve estar ativo para usar a funcionalidade SERP.

= Como faço para exportar todos os posts? =

Acesse 'Ferramentas Upload' → 'Exportar Posts e Categorias' → 'Exportar Posts para CSV'. O arquivo será baixado com todas as informações incluindo conteúdo HTML completo.

= Como recategorizo posts em massa? =

Prepare um CSV com duas colunas: URL do post e nova categoria. Acesse 'Ferramentas Upload' → 'Recategorizar Posts' e faça o upload do arquivo.

= Como mover posts para lixeira em massa? =

Prepare um CSV com uma coluna: URL do post. Acesse 'Ferramentas Upload' → 'Mover Posts para Lixeira' e faça o upload do arquivo.

= Como faço para atualizar os textos alternativos em massa? =

Prepare um arquivo CSV com duas colunas: URL da imagem e texto alternativo desejado. Depois, acesse a aba 'Alt Text' no menu do plugin e faça o upload do arquivo.

= É seguro executar operações em massa? =

Sim, mas SEMPRE faça backup do banco de dados antes. O plugin tem tratamento robusto de erros e validações de segurança.

= Há limites para o número de posts? =

O plugin configura automaticamente limites de memória e tempo. Para hospedagens compartilhadas, recomenda-se lotes de até 1000 posts por vez.

== Screenshots ==

1. Tela principal do plugin com todas as abas
2. Exportação de posts com conteúdo HTML
3. Recategorização em massa via CSV
4. Exclusão segura para lixeira
5. Importação de CSV para atualização de alt text
6. Atualização de meta tags do Yoast SEO

== Changelog ==

= 1.3.0 =
* Adicionada exportação completa de posts com conteúdo HTML
* Implementada recategorização em massa
* Adicionada exclusão segura para lixeira
* Melhorias no tratamento de erros
* Otimizações de performance
* Suporte completo a encoding UTF-8
* Interface administrativa redesenhada

= 1.2.0 =
* Adicionado suporte para atualização de meta tags do Yoast SEO
* Melhorias na performance e estabilidade
* Correções de bugs críticos

= 1.1.0 =
* Adicionada funcionalidade de movimentação em massa para lixeira
* Melhorias na validação de arquivos CSV
* Correções de bugs

= 1.0.0 =
* Lançamento inicial com funcionalidades básicas

== Upgrade Notice ==

= 1.3.0 =
Nova funcionalidade de exportação completa de posts, recategorização em massa e exclusão segura. Melhorias significativas na interface e performance.

== Additional Info ==

Para mais informações, documentação completa e suporte:
* **GitHub:** https://github.com/cagezinho/plugin-wp1
* **Issues:** https://github.com/cagezinho/plugin-wp1/issues
* **Documentação:** Ver arquivo DOCUMENTACAO-COMPLETA.md

**Desenvolvedores:** Adryanno, Augusto, Nicolas Cage