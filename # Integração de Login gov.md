# Integração de Login gov.br e Assinatura Digital gov.br em Sistema de Votação de Câmara Municipal

> Documento técnico para orientar a implantação de autenticação gov.br e assinatura eletrônica avançada gov.br em um sistema de votação legislativa municipal.

---

## 1. Objetivo

Integrar o sistema de votação da Câmara Municipal com:

1. **Login Único gov.br**, para autenticar o vereador ou usuário autorizado.
2. **Assinatura Eletrônica Avançada gov.br**, para assinar digitalmente documentos gerados pelo sistema.
3. **Auditoria técnica**, para registrar CPF autenticado, hash do documento, data/hora, IP, user-agent, voto e arquivo assinado.
4. **Controle jurídico e administrativo**, separando corretamente votação nominal de votação secreta.

---

## 2. Conceitos principais

### 2.1 Login gov.br

O Login Único gov.br serve para identificar o usuário.

Ele permite que o sistema redirecione o usuário para o ambiente gov.br, o usuário faça login, e depois o gov.br retorne para o sistema com um código de autorização.

Depois disso, o sistema troca esse código por um token e consulta os dados do usuário.

Exemplo de dados úteis:

```text
CPF
Nome
Identificador único do usuário
Nível da conta gov.br
Selos de confiabilidade
```

### 2.2 Assinatura Eletrônica Avançada gov.br

A assinatura gov.br não é uma assinatura fixa salva no cadastro.

O correto é:

```text
Usuário autentica pelo gov.br
Sistema gera um documento específico
Usuário autoriza a assinatura daquele documento
Sistema salva o documento assinado ou a assinatura .p7s
```

Ou seja, cada ato importante deve gerar uma assinatura própria.

Exemplos:

```text
Assinatura de voto nominal
Assinatura de ata
Assinatura de presença
Assinatura de parecer
Assinatura de requerimento
Assinatura de indicação
Assinatura de protocolo interno
```

### 2.3 Conta prata ou ouro

Para assinatura eletrônica avançada gov.br, o usuário precisa ter conta gov.br de nível **prata ou ouro**.

Conta bronze normalmente não atende ao fluxo de assinatura avançada.

---

## 3. Quando usar assinatura no sistema de votação

### 3.1 Votação nominal

Em votação nominal, cada voto fica associado ao parlamentar.

Neste caso, é possível assinar o documento do voto.

Exemplo:

```text
Vereador: João da Silva
Matéria: Projeto de Lei nº 012/2026
Voto: SIM
Data/hora: 07/05/2026 10:30
Documento assinado via gov.br
```

### 3.2 Votação secreta

Em votação secreta, o sistema não deve assinar um documento contendo o voto escolhido junto com a identidade do usuário.

Neste caso, o recomendado é:

```text
O vereador assina a presença ou autorização de participação.
O sistema registra que ele participou.
O conteúdo do voto fica separado da identidade.
```

Fluxo recomendado:

```text
Assinatura da presença/autorização
Registro anônimo ou desvinculado do voto
Apuração separada
Auditoria de participação sem expor escolha individual
```

---

## 4. Arquitetura recomendada

```text
[Usuário/Vereador]
        |
        v
[Sistema de Votação]
        |
        |-- Login gov.br
        |-- Validação CPF
        |-- Validação nível gov.br
        |
        v
[Registro do Voto]
        |
        |-- Geração de PDF
        |-- Cálculo de hash SHA-256
        |-- Envio para assinatura gov.br
        |-- Retorno da assinatura
        |
        v
[Banco de Dados + Armazenamento Seguro]
        |
        |-- Voto
        |-- PDF original
        |-- PDF assinado ou .p7s
        |-- Hash
        |-- Logs
        |-- IP
        |-- User-agent
        |-- Data/hora
```

---

## 5. Componentes necessários

### 5.1 No sistema

O sistema precisa ter:

```text
Cadastro de usuários/vereadores
CPF obrigatório no cadastro
Módulo de vinculação gov.br
Tela de login gov.br
Callback de autenticação
Validação de CPF retornado
Validação de nível prata/ouro
Módulo de votação
Geração de documento PDF do voto
Cálculo de hash SHA-256
Callback de assinatura
Registro de assinatura
Logs de auditoria
Painel administrativo de conferência
```

### 5.2 No servidor

O servidor precisa ter:

```text
HTTPS válido
Domínio oficial
Certificado SSL/TLS ativo
Data e hora sincronizadas
NTP ativo
Firewall liberado
IP fixo ou previsível
Ambiente de homologação
Ambiente de produção
Backup automático
Controle de acesso a arquivos assinados
Logs protegidos contra alteração
```

### 5.3 Junto ao gov.br

A Câmara ou órgão responsável precisa solicitar:

```text
Credenciais do Login Único gov.br
Credenciais da API de Assinatura Eletrônica Avançada gov.br
URL de callback de homologação
URL de callback de produção
Escopos necessários
Liberação de IP, se exigido
Homologação técnica
Aprovação para produção
```

---

## 6. Fluxo completo do Login gov.br

### 6.1 Passo a passo

```text
1. Usuário acessa o sistema.
2. Clica em "Entrar com gov.br".
3. Sistema redireciona para a URL de autorização gov.br.
4. Usuário autentica no gov.br.
5. gov.br redireciona para o callback do sistema.
6. Sistema recebe o parâmetro code.
7. Sistema troca o code por access_token.
8. Sistema consulta dados do usuário.
9. Sistema valida CPF.
10. Sistema valida nível da conta.
11. Sistema cria sessão local.
12. Usuário acessa o painel de votação.
```

### 6.2 Validações obrigatórias

```text
O CPF retornado pelo gov.br deve bater com o CPF cadastrado.
O usuário deve estar ativo no sistema.
O perfil deve permitir votação.
O nível gov.br deve ser aceito pelo sistema.
A sessão deve ter expiração curta.
A autenticação gov.br deve ser renovada antes de atos críticos.
```

---

## 7. Fluxo completo da assinatura gov.br

### 7.1 Passo a passo para votação nominal

```text
1. Vereador acessa a sessão legislativa.
2. Sistema confirma que ele está autenticado via gov.br.
3. Vereador seleciona o voto: SIM, NÃO ou ABSTENÇÃO.
4. Sistema grava o voto como PENDENTE_ASSINATURA.
5. Sistema gera um PDF do voto.
6. Sistema calcula o hash SHA-256 do PDF.
7. Sistema inicia fluxo de autorização da assinatura gov.br.
8. Vereador autoriza a assinatura.
9. Sistema envia o hash para a API gov.br.
10. API retorna assinatura.
11. Sistema salva assinatura .p7s ou PDF assinado.
12. Sistema muda status do voto para ASSINADO.
13. Sistema registra logs de auditoria.
```

### 7.2 Estados possíveis do voto

```text
RASCUNHO
PENDENTE_ASSINATURA
ASSINADO
ERRO_ASSINATURA
CANCELADO
ANULADO_ADMINISTRATIVAMENTE
```

---

## 8. Modelo de banco de dados

### 8.1 Tabela de usuários

```sql
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL,

    senha VARCHAR(255) NULL,

    perfil ENUM('admin', 'presidente', 'vereador', 'servidor') NOT NULL DEFAULT 'vereador',

    gov_sub VARCHAR(120) NULL,
    gov_nome VARCHAR(150) NULL,
    gov_cpf VARCHAR(14) NULL,
    gov_nivel VARCHAR(30) NULL,
    gov_vinculado_em DATETIME NULL,
    gov_ultimo_login_em DATETIME NULL,

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,

    UNIQUE KEY uq_usuarios_cpf (cpf),
    UNIQUE KEY uq_usuarios_email (email)
);
```

### 8.2 Tabela de sessões legislativas

```sql
CREATE TABLE sessoes_legislativas (
    id INT AUTO_INCREMENT PRIMARY KEY,

    titulo VARCHAR(200) NOT NULL,
    tipo VARCHAR(100) NULL,
    descricao TEXT NULL,

    data_sessao DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,

    status ENUM('AGENDADA', 'ABERTA', 'SUSPENSA', 'ENCERRADA', 'CANCELADA') NOT NULL DEFAULT 'AGENDADA',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL
);
```

### 8.3 Tabela de matérias

```sql
CREATE TABLE materias (
    id INT AUTO_INCREMENT PRIMARY KEY,

    sessao_id INT NOT NULL,

    numero VARCHAR(50) NULL,
    tipo VARCHAR(100) NULL,
    titulo VARCHAR(255) NOT NULL,
    ementa TEXT NULL,
    autor VARCHAR(150) NULL,

    status ENUM('CADASTRADA', 'EM_DISCUSSÃO', 'EM_VOTACAO', 'APROVADA', 'REJEITADA', 'RETIRADA', 'ENCERRADA') NOT NULL DEFAULT 'CADASTRADA',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,

    INDEX idx_materias_sessao (sessao_id),

    CONSTRAINT fk_materias_sessao
        FOREIGN KEY (sessao_id)
        REFERENCES sessoes_legislativas(id)
        ON DELETE CASCADE
);
```

### 8.4 Tabela de votos

```sql
CREATE TABLE votos (
    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NOT NULL,
    sessao_id INT NOT NULL,
    materia_id INT NOT NULL,

    voto ENUM('SIM', 'NAO', 'ABSTENCAO', 'AUSENTE') NOT NULL,

    tipo_votacao ENUM('NOMINAL', 'SECRETA') NOT NULL DEFAULT 'NOMINAL',

    status_assinatura ENUM('NAO_EXIGIDA', 'PENDENTE', 'ASSINADO', 'ERRO') NOT NULL DEFAULT 'PENDENTE',

    documento_pdf_original VARCHAR(255) NULL,
    documento_pdf_assinado VARCHAR(255) NULL,
    assinatura_p7s VARCHAR(255) NULL,

    documento_hash_sha256 CHAR(64) NULL,

    gov_sub VARCHAR(120) NULL,
    gov_cpf VARCHAR(14) NULL,
    gov_nivel VARCHAR(30) NULL,

    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,

    votado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assinado_em DATETIME NULL DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,

    UNIQUE KEY uq_voto_unico (usuario_id, materia_id),

    INDEX idx_votos_sessao (sessao_id),
    INDEX idx_votos_materia (materia_id),
    INDEX idx_votos_usuario (usuario_id),
    INDEX idx_votos_status_assinatura (status_assinatura),

    CONSTRAINT fk_votos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id),

    CONSTRAINT fk_votos_sessao
        FOREIGN KEY (sessao_id)
        REFERENCES sessoes_legislativas(id),

    CONSTRAINT fk_votos_materia
        FOREIGN KEY (materia_id)
        REFERENCES materias(id)
);
```

### 8.5 Tabela de logs de auditoria

```sql
CREATE TABLE auditoria_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NULL,
    voto_id INT NULL,
    sessao_id INT NULL,
    materia_id INT NULL,

    acao VARCHAR(100) NOT NULL,
    descricao TEXT NULL,

    dados_json JSON NULL,

    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_auditoria_usuario (usuario_id),
    INDEX idx_auditoria_voto (voto_id),
    INDEX idx_auditoria_acao (acao),
    INDEX idx_auditoria_created_at (created_at)
);
```

### 8.6 Tabela de tentativas de assinatura

```sql
CREATE TABLE assinatura_tentativas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    voto_id INT NOT NULL,
    usuario_id INT NOT NULL,

    provider VARCHAR(50) NOT NULL DEFAULT 'GOVBR',

    request_id VARCHAR(120) NULL,
    status ENUM('INICIADA', 'AUTORIZADA', 'ASSINADA', 'ERRO', 'CANCELADA') NOT NULL DEFAULT 'INICIADA',

    documento_hash_sha256 CHAR(64) NULL,

    erro_codigo VARCHAR(100) NULL,
    erro_mensagem TEXT NULL,

    resposta_json JSON NULL,

    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,

    INDEX idx_assinatura_voto (voto_id),
    INDEX idx_assinatura_usuario (usuario_id),
    INDEX idx_assinatura_status (status),

    CONSTRAINT fk_assinatura_tentativas_voto
        FOREIGN KEY (voto_id)
        REFERENCES votos(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assinatura_tentativas_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
);
```

---

## 9. Variáveis de ambiente recomendadas

Arquivo `.env`:

```env
APP_ENV=production
APP_URL=https://votacao.camarax.gov.br

DB_HOST=localhost
DB_NAME=votacao_camara
DB_USER=usuario_banco
DB_PASS=senha_forte

GOVBR_LOGIN_CLIENT_ID=seu_client_id_login
GOVBR_LOGIN_CLIENT_SECRET=seu_client_secret_login
GOVBR_LOGIN_AUTHORIZE_URL=https://sso.acesso.gov.br/authorize
GOVBR_LOGIN_TOKEN_URL=https://sso.acesso.gov.br/token
GOVBR_LOGIN_USERINFO_URL=https://sso.acesso.gov.br/userinfo
GOVBR_LOGIN_CALLBACK_URL=https://votacao.camarax.gov.br/govbr/callback

GOVBR_ASSINATURA_CLIENT_ID=seu_client_id_assinatura
GOVBR_ASSINATURA_CLIENT_SECRET=seu_client_secret_assinatura
GOVBR_ASSINATURA_AUTHORIZE_URL=https://sso.acesso.gov.br/authorize
GOVBR_ASSINATURA_TOKEN_URL=https://sso.acesso.gov.br/token
GOVBR_ASSINATURA_API_URL=https://assinador.iti.br/api
GOVBR_ASSINATURA_CALLBACK_URL=https://votacao.camarax.gov.br/govbr/assinatura/callback

PDF_STORAGE_PATH=/var/www/votacao/storage/pdfs
SIGNATURE_STORAGE_PATH=/var/www/votacao/storage/assinaturas

SESSION_LIFETIME_MINUTES=30
REAUTH_FOR_VOTE_MINUTES=10
```

> Observação: URLs oficiais, escopos e endpoints finais devem ser confirmados no ambiente de homologação fornecido após aprovação da integração.

---

## 10. Estrutura de rotas sugerida

```text
GET  /login
GET  /login/govbr
GET  /govbr/callback

GET  /painel
GET  /sessoes
GET  /sessoes/{id}
GET  /materias/{id}/votar

POST /votos/registrar
GET  /votos/{id}/assinar
GET  /govbr/assinatura/callback

GET  /votos/{id}/documento
GET  /votos/{id}/assinatura
GET  /auditoria/votos/{id}
```

---

## 11. Exemplo de classe PHP para configuração

Arquivo: `config/govbr.php`

```php
<?php

/**
 * Configuração de integração gov.br.
 * Este arquivo centraliza as credenciais e URLs usadas no Login gov.br
 * e na Assinatura Eletrônica Avançada gov.br.
 */

return [

    'login' => [
        'client_id'     => getenv('GOVBR_LOGIN_CLIENT_ID'),
        'client_secret' => getenv('GOVBR_LOGIN_CLIENT_SECRET'),
        'authorize_url' => getenv('GOVBR_LOGIN_AUTHORIZE_URL'),
        'token_url'     => getenv('GOVBR_LOGIN_TOKEN_URL'),
        'userinfo_url'  => getenv('GOVBR_LOGIN_USERINFO_URL'),
        'callback_url'  => getenv('GOVBR_LOGIN_CALLBACK_URL'),
        'scope'         => 'openid email profile govbr_confiabilidades',
    ],

    'assinatura' => [
        'client_id'     => getenv('GOVBR_ASSINATURA_CLIENT_ID'),
        'client_secret' => getenv('GOVBR_ASSINATURA_CLIENT_SECRET'),
        'authorize_url' => getenv('GOVBR_ASSINATURA_AUTHORIZE_URL'),
        'token_url'     => getenv('GOVBR_ASSINATURA_TOKEN_URL'),
        'api_url'       => getenv('GOVBR_ASSINATURA_API_URL'),
        'callback_url'  => getenv('GOVBR_ASSINATURA_CALLBACK_URL'),
        'scope'         => 'signature_session',
    ],

];
```

---

## 12. Exemplo de geração de URL de login gov.br

Arquivo: `GovBrLoginService.php`

```php
<?php

class GovBrLoginService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config['login'];
    }

    public function gerarUrlAutorizacao(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $_SESSION['govbr_login_state'] = $state;
        $_SESSION['govbr_login_nonce'] = $nonce;

        $params = [
            'response_type' => 'code',
            'client_id'     => $this->config['client_id'],
            'redirect_uri'  => $this->config['callback_url'],
            'scope'         => $this->config['scope'],
            'state'         => $state,
            'nonce'         => $nonce,
        ];

        return $this->config['authorize_url'] . '?' . http_build_query($params);
    }
}
```

---

## 13. Exemplo de cálculo de hash SHA-256 do PDF

```php
<?php

function calcularHashSha256Arquivo(string $caminhoArquivo): string
{
    if (!file_exists($caminhoArquivo)) {
        throw new RuntimeException('Arquivo não encontrado para cálculo do hash.');
    }

    if (!is_readable($caminhoArquivo)) {
        throw new RuntimeException('Arquivo sem permissão de leitura.');
    }

    return hash_file('sha256', $caminhoArquivo);
}
```

---

## 14. Exemplo de registro inicial do voto

```php
<?php

function registrarVotoPendenteAssinatura(
    mysqli $conn,
    int $usuarioId,
    int $sessaoId,
    int $materiaId,
    string $voto,
    string $tipoVotacao,
    array $govbrUsuario
): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $statusAssinatura = $tipoVotacao === 'NOMINAL' ? 'PENDENTE' : 'NAO_EXIGIDA';

    $sql = "
        INSERT INTO votos (
            usuario_id,
            sessao_id,
            materia_id,
            voto,
            tipo_votacao,
            status_assinatura,
            gov_sub,
            gov_cpf,
            gov_nivel,
            ip,
            user_agent,
            votado_em,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar INSERT do voto: ' . $conn->error);
    }

    $govSub = $govbrUsuario['sub'] ?? null;
    $govCpf = $govbrUsuario['cpf'] ?? null;
    $govNivel = $govbrUsuario['nivel'] ?? null;

    $stmt->bind_param(
        'iiissssssss',
        $usuarioId,
        $sessaoId,
        $materiaId,
        $voto,
        $tipoVotacao,
        $statusAssinatura,
        $govSub,
        $govCpf,
        $govNivel,
        $ip,
        $userAgent
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao registrar voto: ' . $stmt->error);
    }

    $votoId = $stmt->insert_id;
    $stmt->close();

    return $votoId;
}
```

---

## 15. Documento PDF do voto

O PDF deve conter informações suficientes para auditoria.

Modelo textual recomendado:

```text
CÂMARA MUNICIPAL DE [NOME DO MUNICÍPIO]

TERMO DE REGISTRO DE VOTO ELETRÔNICO

Sessão: [Número/Nome da Sessão]
Data da sessão: [Data]
Matéria: [Tipo/Número/Título]
Vereador: [Nome]
CPF autenticado via gov.br: [CPF mascarado]
Voto registrado: [SIM/NÃO/ABSTENÇÃO]
Data e hora do registro: [Data/hora]
IP de origem: [IP]
Hash SHA-256 do documento: [Hash]

Declaração:
O presente documento registra o voto eletrônico nominal realizado pelo parlamentar identificado acima, mediante autenticação pela conta gov.br e posterior assinatura eletrônica avançada, conforme fluxo de auditoria mantido pelo sistema da Câmara Municipal.

Assinatura:
Documento submetido à Assinatura Eletrônica Avançada gov.br.
```

---

## 16. Regras de segurança

### 16.1 Regras obrigatórias

```text
Usar HTTPS em todo o sistema.
Não permitir votação sem sessão autenticada.
Exigir reautenticação gov.br para atos críticos.
Validar CPF gov.br contra CPF cadastrado.
Não confiar apenas no nome retornado.
Não salvar client_secret em código-fonte.
Não expor tokens em tela, logs ou URL pública.
Usar state e nonce no OAuth.
Validar retorno do state.
Registrar IP, user-agent e data/hora.
Sincronizar horário do servidor via NTP.
Proteger PDFs e assinaturas contra acesso público indevido.
```

### 16.2 Regras contra fraude

```text
Um vereador não pode votar duas vezes na mesma matéria.
Voto assinado não deve ser editado.
Se precisar corrigir, criar ato administrativo de anulação e novo voto.
Guardar histórico completo.
Não sobrescrever arquivos assinados.
Não permitir alteração manual de hash.
Bloquear votação fora do horário/sessão.
Registrar todas as ações administrativas.
```

### 16.3 Armazenamento

Recomendado:

```text
/storage/pdfs/originais/
/storage/pdfs/assinados/
/storage/assinaturas/p7s/
/storage/logs/auditoria/
```

Permissões:

```text
Diretórios fora da pasta pública do site.
Acesso somente via controller autenticado.
Backup criptografado.
Controle de permissões por perfil.
```

---

## 17. Logs mínimos de auditoria

Registrar:

```text
LOGIN_GOVBR_INICIADO
LOGIN_GOVBR_SUCESSO
LOGIN_GOVBR_ERRO
USUARIO_GOVBR_VINCULADO
VOTO_REGISTRADO
PDF_VOTO_GERADO
HASH_PDF_CALCULADO
ASSINATURA_GOVBR_INICIADA
ASSINATURA_GOVBR_AUTORIZADA
ASSINATURA_GOVBR_CONCLUIDA
ASSINATURA_GOVBR_ERRO
VOTO_CONFIRMADO
VOTO_ANULADO
DOCUMENTO_VISUALIZADO
```

Exemplo de JSON no log:

```json
{
  "usuario_id": 15,
  "voto_id": 88,
  "materia_id": 12,
  "acao": "ASSINATURA_GOVBR_CONCLUIDA",
  "hash_sha256": "abc123...",
  "ip": "189.00.00.00",
  "user_agent": "Mozilla/5.0",
  "data_hora": "2026-05-07 10:30:00"
}
```

---

## 18. Telas necessárias

### 18.1 Tela de login

Elementos:

```text
Botão "Entrar com gov.br"
Aviso sobre autenticação
Mensagem de erro quando CPF não confere
Mensagem de erro quando conta não tem nível suficiente
```

### 18.2 Tela de vinculação gov.br

Elementos:

```text
Nome do usuário cadastrado
CPF cadastrado mascarado
Status da vinculação
Botão "Vincular minha conta gov.br"
Última autenticação gov.br
Nível da conta gov.br
```

### 18.3 Tela de votação

Elementos:

```text
Dados da sessão
Dados da matéria
Botões: SIM, NÃO, ABSTENÇÃO
Aviso de que o voto será assinado digitalmente
Confirmação antes do envio
Status da assinatura
```

### 18.4 Tela de confirmação

Elementos:

```text
Voto registrado
Documento gerado
Status da assinatura
Hash do documento
Link para visualizar PDF
Link para baixar assinatura .p7s, se aplicável
```

### 18.5 Painel administrativo

Elementos:

```text
Lista de sessões
Lista de matérias
Lista de votos
Status das assinaturas
Relatório de auditoria
Exportação de ata
Exportação de documentos assinados
```

---

## 19. Checklist para solicitação da integração

Antes de solicitar ao gov.br:

```text
[ ] A Câmara tem domínio institucional definido.
[ ] O sistema está publicado em HTTPS.
[ ] Existe ambiente de homologação.
[ ] Existe ambiente de produção.
[ ] URLs de callback estão definidas.
[ ] Existe responsável técnico.
[ ] Existe responsável institucional.
[ ] Existe política de privacidade.
[ ] Existe termo de uso.
[ ] O fluxo de uso está documentado.
[ ] O sistema tem finalidade pública.
[ ] O IP do servidor é conhecido.
[ ] O sistema não usa WebView para autenticação mobile.
```

Informações que normalmente serão necessárias:

```text
Nome do órgão
CNPJ do órgão
Nome do sistema
Descrição do serviço
URL de homologação
URL de produção
URL de callback de login
URL de callback de assinatura
E-mail técnico
E-mail institucional
Telefone de contato
IP do servidor
Justificativa de uso
Escopos necessários
```

---

## 20. Homologação

Testes mínimos:

```text
Login com usuário válido
Login com CPF diferente do cadastrado
Login com usuário inativo
Login com conta bronze
Login com conta prata
Login com conta ouro
Voto nominal com assinatura concluída
Voto nominal com assinatura cancelada
Voto nominal com erro na assinatura
Tentativa de votar duas vezes
Tentativa de alterar voto assinado
Votação secreta sem vincular identidade ao voto
Geração de PDF
Cálculo de hash
Validação do arquivo .p7s
Auditoria completa do fluxo
```

---

## 21. Cuidados jurídicos e administrativos

Este documento não substitui parecer jurídico.

Antes de colocar em produção, recomenda-se que a Câmara valide:

```text
Regimento interno
Lei Orgânica Municipal
Regras de votação nominal
Regras de votação secreta
Regras de presença remota, se houver
Validade da assinatura avançada no procedimento interno
Forma de arquivamento dos documentos
Prazo de guarda documental
Política de transparência
LGPD e tratamento de CPF
Publicidade dos votos
Sigilo quando aplicável
```

---

## 22. LGPD e proteção de dados

Dados pessoais envolvidos:

```text
Nome
CPF
E-mail
IP
User-agent
Identificador gov.br
Logs de autenticação
Logs de assinatura
Registro de voto
```

Bases e cuidados:

```text
Coletar apenas dados necessários.
Informar finalidade.
Controlar acesso aos dados.
Mascarar CPF em telas públicas.
Evitar exposição de tokens.
Manter logs administrativos protegidos.
Permitir auditoria institucional.
Definir prazo de retenção.
Registrar encarregado/responsável, se aplicável.
```

---

## 23. Exemplo de política de confirmação do voto

Texto sugerido antes de confirmar:

```text
Ao confirmar, seu voto será registrado no sistema eletrônico da Câmara Municipal e, quando se tratar de votação nominal, será gerado um documento de registro de voto para assinatura eletrônica avançada por meio da sua conta gov.br.

Após a conclusão da assinatura, o voto será considerado confirmado e ficará vinculado à matéria, sessão legislativa, data, hora, CPF autenticado, hash do documento e demais registros de auditoria.
```

---

## 24. Exemplo de status para exibir ao usuário

```text
Aguardando confirmação do voto
Gerando documento de registro
Calculando hash do documento
Redirecionando para autorização gov.br
Assinatura autorizada
Assinatura concluída
Voto confirmado com sucesso
Não foi possível concluir a assinatura
Voto pendente de assinatura
```

---

## 25. Estratégia recomendada de implantação

### Fase 1 — Preparação

```text
Revisar regras da Câmara
Definir se as votações serão nominais, secretas ou ambas
Cadastrar vereadores com CPF correto
Criar ambiente de homologação
Publicar HTTPS
Criar política de privacidade
Preparar documentação de solicitação gov.br
```

### Fase 2 — Login gov.br

```text
Solicitar credenciais do Login Único
Implementar botão de login
Implementar callback
Validar CPF
Validar nível da conta
Vincular usuário local ao gov.br
Registrar logs
```

### Fase 3 — Votação

```text
Criar sessões
Criar matérias
Criar tela de votação
Impedir voto duplicado
Registrar voto pendente
Criar auditoria
```

### Fase 4 — PDF e assinatura

```text
Gerar PDF individual do voto
Calcular hash SHA-256
Solicitar autorização de assinatura
Enviar hash para API gov.br
Salvar retorno
Atualizar status do voto
Permitir conferência administrativa
```

### Fase 5 — Produção

```text
Homologar com usuários reais de teste
Validar com jurídico
Validar com controle interno
Treinar servidores
Treinar vereadores
Criar plano de contingência
Ativar produção
Monitorar logs
```

---

## 26. Plano de contingência

Prever situações como:

```text
gov.br indisponível
Usuário sem conta prata/ouro
Usuário esqueceu senha gov.br
Falha na assinatura
Internet instável durante sessão
Erro ao gerar PDF
Erro ao salvar assinatura
Sessão encerrada antes da assinatura
```

Soluções possíveis:

```text
Permitir voto pendente por tempo limitado
Registrar ocorrência administrativa
Gerar relatório de falhas
Permitir nova tentativa de assinatura
Bloquear alteração de conteúdo do voto pendente
Exigir validação da Mesa Diretora em caso excepcional
```

---

## 27. Observações sobre assinatura detached e envelopada

### 27.1 Assinatura detached

A assinatura detached gera dois arquivos:

```text
documento.pdf
documento.p7s
```

Vantagens:

```text
Mais simples para integração
API trabalha com hash do PDF
Arquivo .p7s separado
```

Atenção:

```text
Para validar, precisa do PDF original e do .p7s.
Se o PDF mudar, a assinatura perde validade.
```

### 27.2 Assinatura envelopada

A assinatura envelopada fica dentro do próprio PDF.

Vantagens:

```text
Usuário baixa apenas um PDF assinado.
Mais amigável para conferência.
```

Atenção:

```text
A implementação é mais complexa.
É necessário preparar o PDF com espaço para assinatura.
O hash não é simplesmente o hash do PDF completo.
```

### 27.3 Recomendação inicial

Para primeira versão do sistema:

```text
Usar assinatura detached com PDF + .p7s.
```

Depois, em uma versão mais madura:

```text
Implementar PDF assinado/envelopado.
```

---

## 28. Modelo de documentação para enviar ao órgão/integrador

```text
Sistema: Sistema de Votação Eletrônica da Câmara Municipal de [Município]

Finalidade:
Permitir autenticação segura dos parlamentares e assinatura eletrônica avançada de documentos gerados durante sessões legislativas, especialmente registros de voto nominal, atas, presenças e atos administrativos internos.

Usuários:
Vereadores, presidente da Câmara, servidores autorizados e administradores do sistema.

Dados utilizados:
CPF, nome, e-mail, identificador gov.br, nível da conta, data/hora de autenticação, IP e registros de auditoria.

Justificativa:
Garantir autenticidade, integridade, rastreabilidade e segurança jurídica aos atos digitais praticados no sistema legislativo municipal.

URLs:
Homologação: https://homologacao.seudominio.gov.br
Produção: https://votacao.seudominio.gov.br

Callbacks:
Login: https://votacao.seudominio.gov.br/govbr/callback
Assinatura: https://votacao.seudominio.gov.br/govbr/assinatura/callback
```

---

## 29. Pontos que não devem ser feitos

```text
Não salvar senha gov.br.
Não pedir senha gov.br dentro do seu sistema.
Não guardar token sem criptografia.
Não deixar PDF assinado em pasta pública sem controle.
Não usar CPF como único fator de autenticação.
Não permitir assinatura genérica para todos os votos.
Não assinar voto secreto com identificação do vereador.
Não editar documento depois de assinado.
Não substituir arquivo assinado.
Não ignorar validação do state no OAuth.
Não usar HTTP.
Não usar WebView para login gov.br em aplicativo mobile.
```

---

## 30. Referências oficiais

### Login Único gov.br

- Roteiro técnico do Login Único gov.br:
  - https://acesso.gov.br/roteiro-tecnico/
  - https://acesso.gov.br/roteiro-tecnico/iniciarintegracao.html

### API de Assinatura Eletrônica Avançada gov.br

- Roteiro de integração da API de Assinatura Digital gov.br:
  - https://manual-integracao-assinatura-eletronica.servicos.gov.br/pt_BR/latest/iniciarintegracao.html

### Serviço de integração aos produtos de identidade digital gov.br

- Solicitação de integração aos produtos de identidade digital gov.br:
  - https://www.gov.br/governodigital/pt-br/estrategias-e-governanca-digital/transformacao-digital/servico-de-integracao-aos-produtos-de-identidade-digital-gov.br

---

## 31. Resumo final da implantação

O sistema deve funcionar assim:

```text
1. Vereador é cadastrado com CPF.
2. Vereador vincula sua conta gov.br.
3. Sistema valida CPF e nível da conta.
4. Vereador entra em uma sessão legislativa.
5. Vereador vota em uma matéria.
6. Sistema gera documento PDF do voto.
7. Sistema calcula hash SHA-256.
8. Vereador autoriza assinatura gov.br.
9. Sistema salva assinatura e documento.
10. Voto é confirmado e auditável.
```

Para votação nominal:

```text
Assinar o documento do voto.
```

Para votação secreta:

```text
Assinar apenas presença/autorização, mantendo o voto separado da identidade.
```

---

## 32. Próximo passo recomendado

Antes de codificar a integração definitiva, criar estes documentos internos:

```text
Documento de fluxo funcional
Documento de requisitos técnicos
Termo de uso do sistema
Política de privacidade
Plano de homologação
Parecer jurídico interno
Plano de contingência da sessão legislativa
```

Depois disso, implementar primeiro o Login gov.br e só depois a assinatura gov.br.
