# SENAI Conecta

Rede social web em que os usuários se cadastram, fazem login, veem o feed de publicações, curtem posts e visitam perfis. Usuários do tipo **criador** também podem publicar.

**Tecnologias:** PHP (API com PDO), MariaDB/MySQL, HTML + CSS + JavaScript puro, autenticação por token JWT, XAMPP.

---

## Estrutura do projeto

```
senai_conecta/
├── backend/
│   ├── index.php              # API: todas as rotas
│   ├── .htaccess              # envia as requisições para o index.php
│   ├── config/database.php    # conexão com o banco
│   ├── helpers/jwt_helper.php # geração e validação do token
│   └── uploads/               # fotos de perfil, imagens dos posts e ícones
├── db/
│   ├── senai_conecta.sql      # cria o banco e as tabelas
│   └── usuario.csv            # usuários de teste
└── frontend/
    ├── index.html
    ├── css/style.css
    └── js/                    # api.js, auth.js, app.js
```

---

## Requisitos

- [XAMPP](https://www.apachefriends.org/) com Apache e MySQL/MariaDB (PHP 8.0 ou superior)
- Navegador atualizado
- Git (opcional, só para clonar o repositório)

---

## Instalação

### 1. Colocar o projeto no Apache

Copie a pasta do projeto para dentro do `htdocs` do XAMPP (no Windows: `C:\xampp\htdocs`) ou clone o repositório lá:

```bash
cd C:\xampp\htdocs
git clone https://github.com/guizermiani/senai_conecta.git
```

> A pasta precisa se chamar exatamente **`senai_conecta`**. O backend usa esse nome para montar as rotas.

### 2. Iniciar os serviços

No painel do XAMPP, clique em **Start** em **Apache** e em **MySQL**.

### 3. Ajustar a porta do Apache para 8080

O frontend chama a API em `http://localhost:8080/senai_conecta/backend` (definido em `frontend/js/api.js`). Há duas formas de resolver:

- **Opção A:** no XAMPP, abra **Config → httpd.conf** do Apache, troque `Listen 80` por `Listen 8080`, troque `ServerName localhost:80` por `ServerName localhost:8080` e reinicie o Apache.
- **Opção B:** se preferir manter a porta 80, edite a primeira linha de `frontend/js/api.js` e remova o `:8080` do endereço.

### 4. Criar o banco de dados

1. Abra o phpMyAdmin: `http://localhost:8080/phpmyadmin`
2. Na tela inicial, clique na aba **Importar**.
3. Escolha o arquivo `db/senai_conecta.sql` e clique em **Importar**.

Isso cria o banco `senai_conecta` com as tabelas `usuario`, `publicacao` e `curtida`.

### 5. Importar os usuários de teste

1. No phpMyAdmin, entre no banco `senai_conecta` e abra a tabela `usuario`.
2. Clique em **Importar** e escolha `db/usuario.csv`.
3. Em formato, selecione **CSV**, separador `,`, e marque a opção de que a **primeira linha contém os nomes das colunas**.

### 6. Conferir a conexão

O arquivo `backend/config/database.php` usa o padrão do XAMPP:

| Item | Valor |
|---|---|
| Host | `localhost` |
| Banco | `senai_conecta` |
| Usuário | `root` |
| Senha | (vazia) |

Se o seu MySQL tem outra senha, altere nesse arquivo.

---

## Como acessar

| O quê | Endereço |
|---|---|
| Plataforma | `http://localhost:8080/senai_conecta/frontend/` |
| Teste da API | `http://localhost:8080/senai_conecta/backend/publicacoes` |
| phpMyAdmin | `http://localhost:8080/phpmyadmin` |

Se o endereço de teste da API mostrar um JSON (mesmo que `[]`), o backend e o banco estão funcionando.

---

## Contas de teste

Existem se você importou o `usuario.csv`. A senha de todas é `123456`.

| E-mail | Username | Tipo |
|---|---|---|
| criador1@gmail.com | @criador_1 | criador |
| criador2@gmail.com | @criador_2 | criador |
| criador3@gmail.com | @criador_3 | criador |
| usuario1@gmail.com | @usuario_1 | usuario |
| usuario2@gmail.com | @usuario_2 | usuario |
| usuario3@gmail.com | @usuario_3 | usuario |

---

## Como usar a plataforma

### Sem login (visitante)
- Ver o feed com todas as publicações.
- Abrir o perfil de qualquer usuário.
- Pesquisar usuários.
- Ao tentar curtir, o site abre a janela de login.

### Criar conta
1. Clique em **Entrar** (canto superior direito) e abra a aba **Cadastrar**.
2. Preencha nome, nome de usuário, e-mail e senha. A foto é opcional; sem foto, vale o avatar padrão.
3. Clique em **Cadastrar** e depois faça login. Contas novas são do tipo `usuario`.

### Entrar e sair
- Em **Entrar**, informe e-mail e senha. O login vale por 8 horas.
- Depois de entrar, seu `@username` aparece no topo. Clique nele para abrir o seu perfil.
- Clique em **Sair** para encerrar a sessão.

### Feed
- **Curtir ou descurtir:** clique no coração (♡/♥) da publicação. Clicar de novo remove a curtida.
- **Excluir:** o botão **Excluir** aparece só nas suas próprias publicações.
- **Voltar ao feed:** clique no logo **SENAI CONECTA** ou no botão **← Voltar ao feed** do perfil.

### Publicar (somente usuários do tipo criador)
Quem tem perfil `criador` vê a caixa **Criar Publicação** no topo do feed. Escreva o texto, anexe uma imagem se quiser e clique em **Publicar**.

### Perfil de usuário
Clique no nome ou no `@username` do autor de uma publicação, ou no seu `@` no topo. O perfil mostra:
- avatar, nome e `@username`;
- quantidade de publicações;
- curtidas recebidas;
- a lista das publicações do usuário (dá para curtir e, nas suas, excluir).

### Pesquisar usuários
1. Digite um nome ou `@username` na barra do topo (o `@` é opcional).
2. Aparece uma lista com até 8 resultados, com foto, nome e `@username`.
3. Clique em um resultado para abrir o perfil, ou aperte **Enter** para abrir o primeiro.
4. Clique fora da barra para fechar a lista.

---

## Rotas da API

Base: `http://localhost:8080/senai_conecta/backend`

| Método | Rota | Exige login | O que faz |
|---|---|---|---|
| POST | `/login` | Não | Valida e-mail e senha e devolve o token e os dados do usuário |
| POST | `/cadastro` | Não | Cria um novo usuário (aceita foto) |
| GET | `/publicacoes` | Não | Lista o feed com autor e total de curtidas |
| POST | `/publicacoes` | Sim | Cria uma publicação (texto e imagem opcional) |
| POST | `/curtir` | Sim | Curte ou descurte uma publicação |
| DELETE | `/publicacoes/{id}` | Sim | Exclui uma publicação (só o autor) |
| GET | `/usuarios?busca=texto` | Não | Pesquisa usuários por nome ou username |
| GET | `/usuarios/{id}` | Não | Dados do perfil, contadores e publicações do usuário |

As rotas que exigem login esperam o cabeçalho `Authorization: Bearer <token>`. O frontend já envia isso automaticamente depois do login.

---

## Problemas comuns

| Problema | O que fazer |
|---|---|
| A página abre sem estilo ou o JS não reage às mudanças | Aperte **Ctrl+F5** para limpar o cache |
| "Erro ao carregar o feed" | Confira se Apache e MySQL estão iniciados e se a porta do `api.js` é a mesma do Apache |
| Erro de conexão com o banco | Confira o banco `senai_conecta` e os dados de `backend/config/database.php` |
| "Rota não encontrada" em todas as chamadas | Confirme que a pasta se chama `senai_conecta` e que o `mod_rewrite` está ativo (já vem ativo no XAMPP) |
| Login com "E-mail ou senha inválidos" | Confira se importou o `usuario.csv` e use a senha `123456` |
| Foto não aparece | Se o arquivo não existir em `backend/uploads/`, o site mostra o avatar padrão (`avatar.png`) |
| Upload de imagem não funciona | Confira se a pasta `backend/uploads/` existe e permite gravação |

---

## Licença

Veja o arquivo `LICENSE`.