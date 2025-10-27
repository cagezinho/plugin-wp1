# Plugin Ferramentas Upload - Documentação Completa

**Desenvolvido por:**
- Adryanno
- Augusto  
- Nicolas Cage

**Objetivo:** Plugin WordPress para gerenciar uploads e atualizações em massa, incluindo textos alternativos de imagens, meta tags do Yoast SEO, exportação de posts e categorias, recategorização de posts e movimentação de posts para a lixeira.

**Link para baixar o plugin:** https://github.com/cagezinho/plugin-wp1 - Caso aconteça atualizações, aqui estará a versão mais recente.

## Requisitos

- **WordPress 5.0+**, **PHP 7.2+**
- **Yoast SEO ativo** (free ou premium) - apenas para funcionalidade SERP
- **Recomenda-se fortemente backup do banco de dados** antes de executar qualquer operação

## Onde Acessar no Painel

**Menu:** Ferramentas de Upload → Escolha a aba desejada:
- **"Atualizar Texto Alt"** - Para textos alternativos de imagens
- **"Atualizar SERP Yoast"** - Para meta tags SEO
- **"Exportar Posts e Categorias"** - Para exportação completa
- **"Mover Posts para Lixeira"** - Para exclusão em massa
- **"Recategorizar Posts"** - Para alterar categorias em massa

---

## 📤 EXPORTAÇÃO DE POSTS E CATEGORIAS

### Objetivo
Exportar todos os posts publicados com informações completas para análise, backup ou migração.

### Como Acessar
**Menu:** Ferramentas de Upload → aba **"Exportar Posts e Categorias"**

### Funcionalidades
- Exporta **todos os posts publicados**
- Inclui **conteúdo HTML completo**
- Gera **excerpt automático** se não existir
- **Formato CSV** compatível com Excel/Google Sheets

### Colunas do CSV Exportado
1. **ID do Post** - Identificador único do post
2. **Título do Post** - Título completo
3. **URL do Post** - Link permanente
4. **Categorias** - Lista separada por vírgula
5. **Conteúdo HTML** - HTML completo com todas as tags
6. **Resumo/Excerpt** - Resumo ou excerpt gerado automaticamente

### Como Usar
1. Acesse a aba **"Exportar Posts e Categorias"**
2. Clique em **"Exportar Posts para CSV"**
3. O arquivo será baixado automaticamente
4. Nome do arquivo: `posts_com_conteudo_completo-YYYY-MM-DD_HH-MM-SS.csv`

### Exemplo do CSV Exportado
```csv
ID do Post,Título do Post,URL do Post,Categorias,Conteúdo HTML,Resumo/Excerpt
1,"Como Criar um Blog","https://exemplo.com/blog/","Tutoriais","<p>Neste tutorial...</p>","Neste tutorial você aprenderá..."
2,"Dicas de SEO","https://exemplo.com/seo/","SEO,Marketing","<h2>Palavras-chave</h2>","Otimizar seu site WordPress..."
```

### Limites e Desempenho
- **Sem limite de posts** - exporta todos os publicados
- **Memória otimizada** - usa cache inteligente
- **Arquivos grandes** podem demorar alguns minutos
- **Recomendado:** Teste primeiro com poucos posts

---

## 🏷️ RECATEGORIZAÇÃO DE POSTS

### Objetivo
Alterar as categorias de posts existentes em massa através de um arquivo CSV.

### Como Acessar
**Menu:** Ferramentas de Upload → aba **"Recategorizar Posts"**

### Formato do CSV
**Ordem das colunas:** URL do Post, Nova Categoria (primeira linha é cabeçalho e será ignorada)

**Exemplo:**
```csv
URL,Nova Categoria
https://exemplo.com/post-1,Tecnologia
https://exemplo.com/post-2,Marketing
https://exemplo.com/post-3,Notícias
```

### Passo a Passo para Recategorizar

#### 1. Preparação
- **Faça backup** do banco de dados
- **Identifique os posts** que deseja recategorizar
- **Verifique se as categorias** já existem no WordPress

#### 2. Criação do CSV
- **Coluna A:** URL completa do post (ex: `https://seusite.com/post-exemplo/`)
- **Coluna B:** Nome da nova categoria
- **Salve em UTF-8** com separador vírgula
- **Primeira linha:** cabeçalho (URL, Nova Categoria)

#### 3. Upload e Processamento
1. Acesse **Ferramentas de Upload** → **"Recategorizar Posts"**
2. Clique em **"Escolher arquivo"** e selecione seu CSV
3. Clique em **"Processar CSV de Recategorização"**
4. Aguarde o processamento

#### 4. Verificação dos Resultados
- **Sucessos:** Posts recategorizados com sucesso
- **Avisos:** Categorias que precisam ser criadas
- **Erros:** URLs inválidas ou posts não encontrados

### Como Funciona a Recategorização

1. **Leitura do CSV** linha a linha (pula cabeçalho)
2. **Validação da URL** e busca do post via `url_to_postid`
3. **Verificação da categoria** - se não existir, cria automaticamente
4. **Atualização do post** com a nova categoria
5. **Relatório final** com sucessos, avisos e erros

### Mensagens e Relatórios

#### Sucessos
- **"X post(s) recategorizado(s) com sucesso"**
- **"X categoria(s) criada(s) automaticamente"**

#### Avisos
- **"Categoria 'X' não existia e foi criada"**
- **"Post encontrado mas já tinha a categoria especificada"**

#### Erros
- **"URL inválida ou vazia"**
- **"Post não encontrado para a URL"**
- **"Erro ao processar linha X"**

### Boas Práticas
- **Backup obrigatório** antes de executar
- **Teste com poucos posts** primeiro
- **Verifique as URLs** antes de criar o CSV
- **Use nomes de categorias** exatos (case-sensitive)
- **Evite caracteres especiais** nos nomes das categorias

---

## 🗑️ EXCLUSÃO DE POSTS (MOVER PARA LIXEIRA)

### Objetivo
Mover posts para a lixeira em massa através de um arquivo CSV, sem exclusão permanente.

### Como Acessar
**Menu:** Ferramentas de Upload → aba **"Mover Posts para Lixeira"**

### Formato do CSV
**Uma coluna apenas:** URL do Post (primeira linha é cabeçalho e será ignorada)

**Exemplo:**
```csv
URL
https://exemplo.com/post-antigo-1
https://exemplo.com/post-antigo-2
https://exemplo.com/post-antigo-3
```

### Etapas para Exclusão

#### 1. Preparação
- **Backup do banco de dados** (obrigatório)
- **Identifique os posts** que deseja mover para lixeira
- **Verifique se são posts** (não páginas ou outros tipos)

#### 2. Criação do CSV
- **Uma coluna apenas** com as URLs dos posts
- **URLs completas** (ex: `https://seusite.com/post-exemplo/`)
- **Primeira linha:** cabeçalho (URL)
- **Salve em UTF-8**

#### 3. Processo de Exclusão
1. Acesse **Ferramentas de Upload** → **"Mover Posts para Lixeira"**
2. Clique em **"Escolher arquivo"** e selecione seu CSV
3. Clique em **"Processar CSV de Exclusão"**
4. **Confirme a ação** (operação irreversível)
5. Aguarde o processamento

#### 4. Verificação
- **Posts movidos** aparecerão na lixeira do WordPress
- **Podem ser restaurados** posteriormente se necessário
- **Verifique o relatório** de sucessos e erros

### Como Funciona a Exclusão

1. **Leitura do CSV** linha a linha
2. **Validação da URL** e busca do post
3. **Verificação do tipo** (apenas posts, não páginas)
4. **Movimento para lixeira** via `wp_trash_post()`
5. **Relatório detalhado** do processamento

### Mensagens e Relatórios

#### Sucessos
- **"X post(s) movido(s) para a lixeira com sucesso"**

#### Avisos
- **"Post já estava na lixeira"**
- **"Item não é um post (página/anexo ignorado)"**

#### Erros
- **"URL inválida ou vazia"**
- **"Post não encontrado"**
- **"Erro ao mover post para lixeira"**

### ⚠️ Importante
- **Posts movidos para lixeira** podem ser restaurados
- **Exclusão permanente** deve ser feita manualmente na lixeira
- **Páginas e anexos** são ignorados automaticamente
- **Operação pode ser desfeita** restaurando da lixeira

### Boas Práticas
- **Backup obrigatório** antes de executar
- **Teste com 1-2 posts** primeiro
- **Verifique as URLs** antes de criar o CSV
- **Mantenha o CSV** para referência futura
- **Monitore o espaço** da lixeira do WordPress

---

## 🔧 FUNCIONALIDADES ADICIONAIS

### Atualização de Texto Alt
- **Upload de CSV** com URLs de imagens e textos alternativos
- **Atualização automática** na biblioteca de mídia
- **Atualização em posts** que usam as imagens

### Atualização de SERP (Yoast SEO)
- **Requer Yoast SEO ativo**
- **Atualização de títulos** e meta descrições
- **Formato:** URL, Novo Título, Nova Descrição

---

## 🚨 LIMITES E DESEMPENHO

### Configurações Automáticas
- **Tempo de execução:** 300 segundos (5 minutos)
- **Memória:** 256MB
- **Encoding:** UTF-8 automático

### Recomendações por Hospedagem

#### Hospedagem Compartilhada
- **Máximo 500-1000 posts** por operação
- **Divida arquivos grandes** em lotes menores
- **Execute em horários** de menor tráfego

#### VPS/Dedicado
- **Até 5000 posts** por operação
- **Monitore recursos** do servidor
- **Configure limites** adequados

#### Cloud/Managed
- **Sem limites específicos**
- **Teste primeiro** com amostras
- **Monitore performance**

### Troubleshooting

#### Timeout/Erro 500
- **Reduza o tamanho** do CSV
- **Execute em lotes** menores
- **Verifique logs** de erro

#### Memória Insuficiente
- **Aumente memory_limit** no PHP
- **Reduza posts** por operação
- **Contate suporte** da hospedagem

#### Arquivo Não Processado
- **Verifique formato** do CSV
- **Confirme encoding** UTF-8
- **Teste com arquivo** menor

---

## 📞 SUPORTE E CONTATO

Para dúvidas, bugs ou sugestões:
- **GitHub Issues:** https://github.com/cagezinho/plugin-wp1/issues
- **Documentação:** Este arquivo
- **Versões:** Sempre use a versão mais recente do GitHub

---

## 📋 CHECKLIST DE SEGURANÇA

Antes de executar qualquer operação:

- [ ] **Backup do banco de dados** realizado
- [ ] **Backup dos arquivos** do WordPress
- [ ] **Teste em ambiente** de desenvolvimento
- [ ] **Verificação das URLs** no CSV
- [ ] **Formato correto** do arquivo CSV
- [ ] **Encoding UTF-8** confirmado
- [ ] **Permissões adequadas** no servidor
- [ ] **Monitoramento** durante execução

**⚠️ Lembre-se: Sempre faça backup antes de executar operações em massa!**
