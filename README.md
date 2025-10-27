# Plugin Ferramentas Upload WordPress

![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.2+-green.svg)
![License](https://img.shields.io/badge/License-GPL%20v2%2B-red.svg)

Plugin WordPress para gerenciar uploads e atualizações em massa, incluindo textos alternativos de imagens, meta tags do Yoast SEO, exportação de posts e categorias, recategorização de posts e movimentação de posts para a lixeira.

## 🚀 Funcionalidades

### 📤 Exportação Completa de Posts
- Exporta todos os posts publicados com conteúdo HTML completo
- Inclui categorias, URLs e excerpts automáticos
- Formato CSV compatível com Excel/Google Sheets
- Sem limite de posts

### 🏷️ Recategorização em Massa
- Altera categorias de posts através de CSV
- Cria categorias automaticamente se não existirem
- Validação de URLs e posts
- Relatório detalhado de sucessos e erros

### 🗑️ Exclusão Segura (Lixeira)
- Move posts para lixeira em massa
- Permite restauração posterior
- Ignora páginas e anexos automaticamente
- Operação reversível

### 🖼️ Textos Alternativos de Imagens
- Atualiza alt text de imagens em massa
- Atualiza automaticamente em posts que usam as imagens
- Suporte a encoding UTF-8
- Validação de URLs de imagens

### 🔍 Meta Tags SEO (Yoast)
- Atualiza títulos e meta descrições do Yoast SEO
- Requer Yoast SEO ativo
- Validação de URLs e posts
- Suporte a caracteres especiais

## 📋 Requisitos

- **WordPress 5.0+**
- **PHP 7.2+**
- **Yoast SEO** (apenas para funcionalidade SERP)
- **Backup do banco de dados** (altamente recomendado)

## 🛠️ Instalação

### Via GitHub (Recomendado)
1. Baixe o plugin do [GitHub](https://github.com/cagezinho/plugin-wp1)
2. Extraia o arquivo ZIP
3. Faça upload da pasta para `/wp-content/plugins/`
4. Ative o plugin no painel administrativo

### Via Upload Manual
1. Acesse **Plugins** → **Adicionar Novo** → **Enviar Plugin**
2. Selecione o arquivo ZIP do plugin
3. Clique em **Instalar Agora**
4. Ative o plugin

## 📖 Como Usar

### 1. Exportação de Posts
```
Menu: Ferramentas de Upload → "Exportar Posts e Categorias"
Botão: "Exportar Posts para CSV"
```

**Colunas exportadas:**
- ID do Post
- Título do Post  
- URL do Post
- Categorias
- Conteúdo HTML
- Resumo/Excerpt

### 2. Recategorização
```
Menu: Ferramentas de Upload → "Recategorizar Posts"
Formato CSV: URL, Nova Categoria
```

**Exemplo CSV:**
```csv
URL,Nova Categoria
https://exemplo.com/post-1,Tecnologia
https://exemplo.com/post-2,Marketing
```

### 3. Exclusão (Lixeira)
```
Menu: Ferramentas de Upload → "Mover Posts para Lixeira"
Formato CSV: URL (uma coluna apenas)
```

**Exemplo CSV:**
```csv
URL
https://exemplo.com/post-antigo-1
https://exemplo.com/post-antigo-2
```

### 4. Textos Alternativos
```
Menu: Ferramentas de Upload → "Atualizar Texto Alt"
Formato CSV: URL da Imagem, Alt Text
```

### 5. Meta Tags SEO
```
Menu: Ferramentas de Upload → "Atualizar SERP Yoast"
Formato CSV: URL, Novo Título, Nova Descrição
```

## ⚙️ Configurações Automáticas

O plugin configura automaticamente:
- **Tempo de execução:** 300 segundos
- **Memória:** 256MB
- **Encoding:** UTF-8
- **Tratamento de erros:** Completo

## 🔒 Segurança

- **Validação de nonces** em todas as operações
- **Sanitização** de dados de entrada
- **Escape** de saída HTML
- **Verificação de permissões** administrativas
- **Tratamento de erros** robusto

## 📊 Limites e Performance

### Hospedagem Compartilhada
- **Máximo:** 500-1000 posts por operação
- **Recomendação:** Divida arquivos grandes

### VPS/Dedicado  
- **Máximo:** 5000 posts por operação
- **Monitoramento:** Recursos do servidor

### Cloud/Managed
- **Sem limites específicos**
- **Teste primeiro** com amostras

## 🐛 Troubleshooting

### Erro 500/Timeout
- Reduza o tamanho do CSV
- Execute em lotes menores
- Verifique logs de erro

### Memória Insuficiente
- Aumente `memory_limit` no PHP
- Reduza posts por operação
- Contate suporte da hospedagem

### Arquivo Não Processado
- Verifique formato do CSV
- Confirme encoding UTF-8
- Teste com arquivo menor

## 📁 Estrutura do Plugin

```
plugin-wp1/
├── ferramentas-upload.php          # Arquivo principal
├── includes/
│   ├── class-plugin-loader.php     # Carregador do plugin
│   ├── class-admin-page.php        # Interface administrativa
│   ├── class-post-exporter.php     # Exportação de posts
│   ├── class-post-category-handler.php # Recategorização
│   ├── class-post-trash-handler.php    # Exclusão
│   ├── class-alt-text-handler.php      # Textos alternativos
│   └── class-serp-handler.php          # Meta tags SEO
├── README.md
└── readme.txt
```

## 🤝 Contribuição

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

## 📝 Changelog

### v1.3.0
- ✅ Exportação completa com conteúdo HTML
- ✅ Recategorização em massa
- ✅ Exclusão segura para lixeira
- ✅ Tratamento robusto de erros
- ✅ Suporte a encoding UTF-8
- ✅ Otimizações de performance

### v1.2.0
- ✅ Textos alternativos de imagens
- ✅ Meta tags SEO (Yoast)
- ✅ Interface administrativa melhorada

### v1.1.0
- ✅ Exportação básica de posts
- ✅ Validação de arquivos CSV

### v1.0.0
- ✅ Versão inicial
- ✅ Estrutura básica do plugin

## 📞 Suporte

- **GitHub Issues:** [Reportar bugs](https://github.com/cagezinho/plugin-wp1/issues)
- **Documentação:** [Ver documentação completa](DOCUMENTACAO-COMPLETA.md)
- **Versões:** Sempre use a versão mais recente

## 📄 Licença

Este plugin está licenciado sob a [GPL v2 ou posterior](https://www.gnu.org/licenses/gpl-2.0.html).

## 👥 Desenvolvedores

- **Adryanno**
- **Augusto**
- **Nicolas Cage**

---

## ⚠️ Aviso Importante

**SEMPRE faça backup do banco de dados antes de executar operações em massa!**

Este plugin modifica dados diretamente no banco de dados. Embora todas as operações sejam reversíveis (exceto exclusão permanente), é fundamental ter um backup atualizado antes de qualquer operação em massa.