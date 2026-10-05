# Foco Hotel API

API REST para gerenciamento de hotéis, quartos, reservas, hóspedes, pagamentos e cupons, desenvolvida em Laravel como parte de um desafio técnico.

O projeto foi construído com foco não apenas no funcionamento dos endpoints, mas também em **organização de código, separação de responsabilidades, integridade de dados, regras de negócio, idempotência, segurança, concorrência, testes automatizados, documentação e facilidade de evolução**.

---

## Sumário

* [Sobre o projeto](#sobre-o-projeto)
* [Requisitos do desafio](#requisitos-do-desafio)
* [Principais funcionalidades](#principais-funcionalidades)
* [Tecnologias](#tecnologias)
* [Arquitetura](#arquitetura)
* [Pré-requisitos](#pré-requisitos)
* [Execução com Docker](#execução-com-docker)
* [Instalação local](#instalação-local)
* [Configuração do ambiente](#configuração-do-ambiente)
* [Banco de dados](#banco-de-dados)
* [Importação dos XMLs](#importação-dos-xmls)
* [Idempotência da importação](#idempotência-da-importação)
* [Tratamento de inconsistências](#tratamento-de-inconsistências)
* [Scheduler e CRON](#scheduler-e-cron)
* [Autenticação](#autenticação)
* [Usuário de teste](#usuário-de-teste)
* [API REST](#api-rest)
* [API de quartos](#api-de-quartos)
* [API de reservas](#api-de-reservas)
* [Regras de disponibilidade](#regras-de-disponibilidade)
* [Cálculo financeiro](#cálculo-financeiro)
* [Cupons e promoções](#cupons-e-promoções)
* [API de pagamentos](#api-de-pagamentos)
* [Concorrência e transações](#concorrência-e-transações)
* [Tratamento de erros](#tratamento-de-erros)
* [Swagger / OpenAPI](#swagger--openapi)
* [Testes automatizados](#testes-automatizados)
* [Estrutura do projeto](#estrutura-do-projeto)
* [Principais decisões técnicas](#principais-decisões-técnicas)
* [Validação dos dados importados](#validação-dos-dados-importados)
* [Comandos úteis](#comandos-úteis)
* [Status do desafio](#status-do-desafio)
* [Possíveis evoluções](#possíveis-evoluções)
* [Considerações finais](#considerações-finais)

---

# Sobre o projeto

O **Foco Hotel API** é uma API REST desenvolvida em **PHP 8.5 e Laravel 13** para gerenciamento de dados hoteleiros.

O sistema foi desenvolvido a partir dos XMLs fornecidos no desafio e posteriormente evoluído para contemplar regras de negócio relacionadas a reservas, disponibilidade, quantidade de unidades, descontos, pagamentos e autenticação.

O projeto possui dois fluxos principais:

### Importação de dados

Os dados fornecidos em XML são processados por um comando Artisan, validados e persistidos no banco de dados.

```text
XML
 │
 ▼
Artisan Command
 │
 ▼
XmlImportService
 │
 ├── HotelImportService
 ├── RoomImportService
 └── ReservationImportService
 │
 ▼
Models / Eloquent
 │
 ▼
Banco de dados
```

### Operações da API

As operações realizadas pelos consumidores da API seguem uma separação semelhante:

```text
HTTP Request
 │
 ▼
Middleware de autenticação
 │
 ▼
Form Request
 │
 ▼
Controller
 │
 ▼
Service
 │
 ▼
Model / Database
 │
 ▼
API Resource
 │
 ▼
JSON Response
```

Essa separação evita concentrar regras de negócio nos Controllers e facilita testes, manutenção e evolução.

---

# Requisitos do desafio

O projeto atende os principais requisitos propostos:

* documentação do processo de importação;
* modelagem do banco de dados baseada nos XMLs;
* comando PHP/Laravel para importação dos XMLs;
* execução periódica através do Laravel Scheduler e CRON;
* importação idempotente;
* tratamento de inconsistências;
* CRUD REST de quartos;
* criação de reservas;
* cálculo financeiro no backend;
* respostas JSON;
* autenticação;
* documentação Swagger/OpenAPI 3;
* testes automatizados;
* Docker;
* controle de concorrência;
* gerenciamento de pagamentos;
* cupons e promoções.

Além dos requisitos principais, foram implementadas preocupações adicionais relacionadas a:

* integridade referencial;
* transações;
* bloqueio de registros durante operações críticas;
* proteção contra overbooking;
* proteção contra pagamentos acima do saldo;
* separação entre identificadores internos e externos;
* preservação de inconsistências encontradas nos XMLs;
* validação de regras de negócio;
* padronização de respostas.

---

# Principais funcionalidades

O projeto atualmente possui:

* importação de hotéis, quartos e reservas através de XML;
* importação idempotente;
* identificação de inconsistências durante a importação;
* armazenamento das inconsistências em `import_errors`;
* relacionamento entre hotéis, quartos, reservas e hóspedes;
* controle de quantidade de unidades por quarto;
* criação de reservas através da API;
* validação de disponibilidade;
* suporte a reservas consecutivas;
* cálculo de subtotal;
* cálculo de descontos;
* cálculo de taxas;
* cálculo do total;
* cupons percentuais;
* cupons de valor fixo;
* validade de cupons;
* controle de cupons ativos/inativos;
* gerenciamento de pagamentos;
* cálculo do total pago;
* cálculo do saldo restante;
* prevenção de pagamentos acima do saldo;
* autenticação via Laravel Sanctum;
* documentação OpenAPI 3;
* testes automatizados com PHPUnit;
* Docker com PHP-FPM, Nginx e MySQL;
* respostas JSON;
* tratamento de erros HTTP;
* controle de concorrência através de transações e `lockForUpdate()`.

---

# Tecnologias

| Tecnologia      | Utilização                               |
| --------------- | ---------------------------------------- |
| PHP 8.5         | Linguagem principal                      |
| Laravel 13      | Framework da aplicação                   |
| MySQL 8         | Banco de dados principal                 |
| SQLite          | Banco utilizado nos testes automatizados |
| Composer        | Gerenciamento de dependências            |
| PHPUnit         | Testes automatizados                     |
| Laravel Sanctum | Autenticação por Bearer Token            |
| L5-Swagger      | Geração da documentação OpenAPI          |
| Swagger UI      | Visualização e teste da API              |
| Docker          | Containerização do ambiente              |
| Nginx           | Servidor HTTP no ambiente Docker         |
| Git             | Controle de versão                       |

---

# Arquitetura

A aplicação utiliza uma arquitetura baseada na separação de responsabilidades entre as camadas.

## Controllers

Os Controllers recebem as requisições HTTP e coordenam a execução da operação.

Eles não concentram as principais regras de negócio.

Exemplo:

```text
ReservationController
        │
        ▼
CreateReservationService
        │
        ├── AvailabilityService
        ├── PricingService
        └── CouponService
```

Isso permite que as regras de reserva sejam testadas e reutilizadas sem depender diretamente de uma requisição HTTP.

---

## Form Requests

Os Form Requests são responsáveis pela validação dos dados recebidos pela API.

Exemplos:

* `StoreRoomRequest`
* `UpdateRoomRequest`
* `StoreReservationRequest`
* `StorePaymentRequest`

A validação estrutural fica próxima da entrada da aplicação, enquanto regras específicas de domínio permanecem nos Services.

Por exemplo, o `StoreReservationRequest` verifica se uma diária possui formato válido e se pertence ao período da reserva.

Já a validação de um cupom existente, ativo e dentro do prazo é responsabilidade do `CouponService`.

---

## Services

Os Services concentram regras de negócio.

Entre os principais:

```text
Services/
├── Import/
│   ├── XmlImportService
│   ├── HotelImportService
│   ├── RoomImportService
│   ├── ReservationImportService
│   └── ImportErrorRecorder
│
└── Reservation/
    ├── CreateReservationService
    ├── AvailabilityService
    ├── PricingService
    ├── CouponService
    └── PaymentService
```

Essa divisão evita que Controllers se tornem responsáveis por cálculos, validações de disponibilidade, importação ou operações financeiras.

---

## API Resources

Os Resources controlam a representação dos dados retornados pela API.

Isso permite definir quais campos serão expostos e quais relacionamentos serão incluídos quando necessário.

---

# Pré-requisitos

Para execução local:

* PHP 8.5;
* Composer;
* MySQL 8;
* Git.

Para execução com Docker:

* Docker Desktop;
* Docker Compose;
* virtualização habilitada.

---

# Execução com Docker

O projeto possui ambiente Docker composto por:

```text
┌─────────────────────┐
│       Nginx         │
│      porta 8000     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│     PHP-FPM         │
│    Laravel 13       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│      MySQL 8        │
└─────────────────────┘
```

Os serviços principais são:

* `app`: PHP-FPM + Laravel;
* `nginx`: servidor HTTP;
* `mysql`: banco de dados MySQL 8.

## Subir o ambiente

```bash
docker compose up -d --build
```

Verificar os containers:

```bash
docker compose ps
```

O MySQL possui healthcheck para garantir que o serviço esteja disponível antes da aplicação depender dele.

## Executar migrations

```bash
docker compose exec app php artisan migrate
```

## Importar os XMLs

```bash
docker compose exec app php artisan hotel:import
```

## Executar testes

```bash
docker compose exec app php artisan test
```

A aplicação fica disponível em:

```text
http://127.0.0.1:8000
```

A documentação Swagger fica disponível em:

```text
http://127.0.0.1:8000/api/documentation
```

---

# Instalação local

## 1. Clonar o projeto

```bash
git clone <url-do-repositorio>
cd desafio-foco-hotel-api
```

## 2. Instalar dependências

```bash
composer install
```

## 3. Criar o `.env`

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Gerar a chave:

```bash
php artisan key:generate
```

## 4. Configurar o banco

Exemplo:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=foco_hotel
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

A porta `3307` corresponde ao ambiente de desenvolvimento utilizado durante a implementação. Em uma instalação padrão do MySQL, a porta normalmente é `3306`.

## 5. Criar o banco

```sql
CREATE DATABASE foco_hotel
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

## 6. Executar migrations

```bash
php artisan migrate
```

## 7. Importar os XMLs

Coloque os arquivos no diretório:

```text
storage/app/imports/
```

E execute:

```bash
php artisan hotel:import
```

## 8. Iniciar a aplicação

```bash
php artisan serve
```

A aplicação ficará disponível, por padrão, em:

```text
http://127.0.0.1:8000
```

---

# Configuração do ambiente

As principais variáveis relacionadas ao banco são:

| Variável        | Descrição        |
| --------------- | ---------------- |
| `DB_CONNECTION` | Driver utilizado |
| `DB_HOST`       | Host do banco    |
| `DB_PORT`       | Porta do banco   |
| `DB_DATABASE`   | Banco utilizado  |
| `DB_USERNAME`   | Usuário do banco |
| `DB_PASSWORD`   | Senha do banco   |

O `.env` não deve ser versionado.

O Docker utiliza variáveis específicas para comunicação interna entre containers. Nesse ambiente, o Laravel se comunica com o serviço MySQL através do nome do serviço Docker e da porta interna do MySQL.

---

# Banco de dados

A modelagem foi criada considerando os dados presentes nos XMLs e as regras adicionais necessárias para o funcionamento da API.

| Tabela                   | Responsabilidade                 |
| ------------------------ | -------------------------------- |
| `hotels`                 | Hotéis                           |
| `rooms`                  | Quartos e quantidade de unidades |
| `reservations`           | Reservas                         |
| `guests`                 | Hóspedes                         |
| `reservation_dailies`    | Diárias das reservas             |
| `payments`               | Pagamentos                       |
| `coupons`                | Cupons                           |
| `reservation_coupons`    | Cupons efetivamente aplicados    |
| `import_errors`          | Inconsistências da importação    |
| `users`                  | Usuários da API                  |
| `personal_access_tokens` | Tokens do Laravel Sanctum        |

## Relacionamentos principais

```text
Hotel
 └── hasMany Rooms
       └── hasMany Reservations
             ├── hasMany Guests
             ├── hasMany ReservationDailies
             ├── hasMany Payments
             └── belongsToMany Coupons
```

---

# Identificadores externos

Os IDs presentes nos XMLs não são utilizados como chave primária das tabelas.

Eles são armazenados em `external_id`.

Por exemplo, um quarto vindo do XML pode possuir:

```text
external_id = 1
```

enquanto seu ID interno no banco pode ser:

```text
id = 7
```

Essa separação é importante porque o ID interno pertence ao banco, enquanto o `external_id` pertence ao sistema de origem dos dados.

Os relacionamentos internos utilizam as chaves primárias do banco.

Os `external_id` são utilizados principalmente para:

* localizar registros importados;
* atualizar registros existentes;
* impedir duplicações;
* permitir reexecução da importação.

---

# Importação dos XMLs

Os arquivos esperados são:

```text
hotels.xml
rooms.xml
reserves.xml
```

O comando responsável é:

```bash
php artisan hotel:import
```

O diretório padrão é:

```text
storage/app/imports
```

Também é possível informar outro diretório:

```bash
php artisan hotel:import --path=/caminho/dos/xmls
```

---

# Fluxo da importação

A importação é executada em uma ordem que respeita as dependências dos dados:

```text
Hotels
   ↓
Rooms
   ↓
Reservations
```

Isso ocorre porque:

* quartos dependem de hotéis;
* reservas dependem de hotéis e quartos;
* hóspedes, diárias e pagamentos dependem de reservas.

O `XmlImportService` coordena esse fluxo.

Cada tipo de entidade possui seu próprio serviço especializado.

---

# Validação dos XMLs

Antes da importação, os arquivos são:

* localizados;
* verificados quanto à existência;
* verificados quanto à possibilidade de leitura;
* processados através do SimpleXML;
* protegidos contra carregamento de recursos externos;
* validados quanto à estrutura esperada.

A importação não tenta simplesmente inserir qualquer conteúdo recebido.

Arquivos inválidos provocam falha controlada do processo, enquanto inconsistências de registros válidos podem ser registradas individualmente.

---

# Idempotência

A importação foi desenvolvida para ser executada mais de uma vez sem gerar duplicações.

Isso é realizado principalmente através dos identificadores externos e operações de atualização/criação.

Exemplo conceitual:

```text
Primeira execução
XML → registro não existe → CREATE

Segunda execução
XML → registro já existe → UPDATE

Terceira execução
XML → registro já existe → UPDATE
```

Essa característica é importante porque o processo é executado automaticamente pelo Scheduler.

---

# Tratamento de inconsistências

Uma decisão importante do projeto foi **não corrigir silenciosamente dados inconsistentes recebidos da origem**.

Quando uma inconsistência de negócio é identificada:

1. o dado original pode ser preservado;
2. a inconsistência é registrada;
3. o processamento dos demais registros continua quando possível.

As inconsistências são armazenadas em:

```text
import_errors
```

---

# Exemplo real do XML

A reserva externa `6` possui:

```text
Check-in:  2022-10-01
Check-out: 2022-10-04
```

Como o check-in é inclusivo e o check-out exclusivo, as noites esperadas são:

```text
2022-10-01
2022-10-02
2022-10-03
```

Entretanto, o XML também contém uma diária em:

```text
2022-12-03
```

Essa data está fora do período da reserva.

O sistema não remove a diária nem altera silenciosamente o XML importado.

A inconsistência é registrada em `import_errors` com:

```text
type = daily_outside_stay_period
```

Isso permite preservar a informação de origem e, ao mesmo tempo, tornar o problema auditável.

Após a importação dos arquivos fornecidos, o banco possui:

```text
Hotéis:             3
Quartos:            6
Reservas:           6
Hóspedes:           6
Diárias:            18
Pagamentos:         1
Inconsistências:    1
```

---

# Scheduler e CRON

O Laravel Scheduler está configurado para executar a importação uma vez por hora.

A tarefa utiliza:

```text
hourly()
withoutOverlapping()
```

## `hourly()`

Define a frequência de execução da importação.

## `withoutOverlapping()`

Evita que uma segunda execução da mesma tarefa seja iniciada enquanto outra ainda estiver em execução.

Isso é especialmente importante porque a importação modifica dados persistidos.

---

# CRON x Scheduler x Artisan

Os três componentes possuem responsabilidades diferentes:

| Componente        | Responsabilidade                          |
| ----------------- | ----------------------------------------- |
| CRON              | Aciona o Scheduler periodicamente         |
| Laravel Scheduler | Decide quais tarefas devem ser executadas |
| `hotel:import`    | Executa a lógica efetiva de importação    |

Em produção, o sistema operacional pode executar o Scheduler a cada minuto:

```text
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para verificar as tarefas:

```bash
php artisan schedule:list
```

Durante desenvolvimento:

```bash
php artisan schedule:work
```

---

# Autenticação

A API utiliza Laravel Sanctum.

O endpoint de login é público:

```text
POST /api/login
```

Após autenticação, o usuário recebe um Bearer Token.

As requisições protegidas devem enviar:

```text
Authorization: Bearer {token}
Accept: application/json
```

Sem autenticação válida, a API retorna:

```text
401 Unauthorized
```

---

# Usuário de teste

Para facilitar a avaliação do projeto, existe um usuário de teste:

| Campo  | Valor            |
| ------ | ---------------- |
| Nome   | Usuário Teste    |
| E-mail | `teste@foco.com` |
| Senha  | `12345678`       |

Login:

```text
POST /api/login
```

Exemplo:

```json
{
    "email": "teste@foco.com",
    "password": "12345678"
}
```

A senha acima é destinada exclusivamente ao usuário de teste do projeto.

Em um ambiente real, as credenciais deveriam ser substituídas e nunca utilizadas como credenciais de produção.

---

# Rotas protegidas

As operações abaixo exigem autenticação:

```text
GET    /api/user

GET    /api/rooms
POST   /api/rooms
GET    /api/rooms/{room}
PUT    /api/rooms/{room}
PATCH  /api/rooms/{room}
DELETE /api/rooms/{room}

POST   /api/reservations

GET    /api/reservations/{reservation}/payments
POST   /api/reservations/{reservation}/payments
GET    /api/reservations/{reservation}/payments/{payment}
DELETE /api/reservations/{reservation}/payments/{payment}
```

---

# API REST

A API utiliza o prefixo:

```text
/api
```

As rotas seguem os verbos HTTP adequados para suas respectivas operações.

As respostas são retornadas em JSON.

As rotas disponíveis podem ser verificadas através de:

```bash
php artisan route:list
```

---

# API de quartos

A API de quartos utiliza os métodos REST tradicionais.

| Método   | Endpoint            | Finalidade       |
| -------- | ------------------- | ---------------- |
| `GET`    | `/api/rooms`        | Listar quartos   |
| `POST`   | `/api/rooms`        | Criar quarto     |
| `GET`    | `/api/rooms/{room}` | Consultar quarto |
| `PUT`    | `/api/rooms/{room}` | Atualizar quarto |
| `PATCH`  | `/api/rooms/{room}` | Atualizar quarto |
| `DELETE` | `/api/rooms/{room}` | Remover quarto   |

A listagem utiliza paginação.

## Campos

| Campo         | Descrição                          |
| ------------- | ---------------------------------- |
| `hotel_id`    | ID interno do hotel                |
| `external_id` | ID externo do quarto               |
| `name`        | Nome do quarto                     |
| `quantity`    | Quantidade de unidades disponíveis |

A combinação:

```text
hotel_id + external_id
```

deve ser única.

## PUT e PATCH

O endpoint PATCH está disponível para manter a interface REST da API, porém a implementação atual utiliza o mesmo Form Request do PUT.

Consequentemente, os campos considerados obrigatórios pelo `UpdateRoomRequest` continuam sendo exigidos no PATCH.

Isso é uma limitação conhecida e intencional da implementação atual.

---

# API de reservas

O endpoint para criação de reservas é:

```text
POST /api/reservations
```

A criação passa pelas seguintes etapas conceituais:

```text
Request
 ↓
Validação estrutural
 ↓
Localização do quarto
 ↓
Validação hotel/quarto
 ↓
Bloqueio do quarto
 ↓
Verificação de disponibilidade
 ↓
Cálculo do subtotal
 ↓
Aplicação do cupom
 ↓
Cálculo do total
 ↓
Criação da reserva
 ↓
Criação dos hóspedes
 ↓
Criação das diárias
 ↓
Registro do cupom aplicado
```

Todo esse fluxo ocorre dentro de uma transação.

---

# Dados da reserva

| Campo         | Obrigatório | Descrição        |
| ------------- | ----------- | ---------------- |
| `hotel_id`    | Sim         | Hotel da reserva |
| `room_id`     | Sim         | Quarto reservado |
| `check_in`    | Sim         | Data de entrada  |
| `check_out`   | Sim         | Data de saída    |
| `guests`      | Sim         | Hóspedes         |
| `dailies`     | Sim         | Diárias          |
| `coupon_code` | Não         | Cupom utilizado  |

---

# Regras das diárias

As diárias devem:

* possuir datas únicas;
* utilizar o formato `Y-m-d`;
* estar dentro do período da reserva;
* não possuir data anterior ao check-in;
* não possuir data igual ou posterior ao check-out;
* possuir quantidade equivalente ao número de noites.

Exemplo:

```text
Check-in:  10/11
Check-out: 13/11
```

As noites são:

```text
10/11
11/11
12/11
```

Portanto, são necessárias três diárias.

A data de check-out não representa uma noite.

---

# Regra hotel x quarto

O `room_id` não é aceito apenas porque existe.

O quarto também precisa pertencer ao `hotel_id` informado.

Por exemplo:

```text
hotel_id = 1
room_id  = quarto pertencente ao hotel 2
```

Essa combinação é rejeitada.

Essa verificação é realizada no Service, além da validação estrutural feita pelo Form Request.

---

# Valores financeiros

A API não confia em valores financeiros enviados pelo cliente.

Campos como:

```text
subtotal
discount
fees
total
```

são calculados pelo backend.

Portanto, mesmo que o consumidor tente enviar um valor diferente, ele não consegue determinar o total da reserva.

A fonte de verdade financeira é o servidor.

---

# Regra de cálculo

O subtotal corresponde à soma das diárias:

```text
subtotal = soma das diárias
```

O desconto é determinado pelo cupom, quando existente.

As taxas atualmente começam em zero.

O total segue:

```text
total = subtotal - discount + fees
```

---

# Valores monetários

Os cálculos financeiros são realizados utilizando centavos inteiros.

Conceitualmente:

```text
R$ 100,50
↓
10.050 centavos
```

Isso evita depender diretamente de operações com ponto flutuante para as comparações financeiras.

O arredondamento utilizado nos cálculos monetários é `half-up`.

---

# API de pagamentos

O projeto possui gerenciamento interno de pagamentos associados às reservas.

Importante: esse módulo **não representa uma integração com gateway ou adquirente de pagamentos**.

Ele controla os registros financeiros da aplicação.

---

# Endpoints de pagamentos

| Método   | Endpoint                                             | Finalidade          |
| -------- | ---------------------------------------------------- | ------------------- |
| `GET`    | `/api/reservations/{reservation}/payments`           | Listar pagamentos   |
| `POST`   | `/api/reservations/{reservation}/payments`           | Registrar pagamento |
| `GET`    | `/api/reservations/{reservation}/payments/{payment}` | Consultar pagamento |
| `DELETE` | `/api/reservations/{reservation}/payments/{payment}` | Excluir pagamento   |

Todos exigem autenticação.

---

# Registrar pagamento

Endpoint:

```text
POST /api/reservations/{reservation}/payments
```

Campos:

| Campo     | Obrigatório | Descrição              |
| --------- | ----------- | ---------------------- |
| `method`  | Sim         | Forma de pagamento     |
| `amount`  | Sim         | Valor do pagamento     |
| `paid_at` | Não         | Data/hora do pagamento |

Exemplo:

```json
{
    "method": "credit_card",
    "amount": 100.00,
    "paid_at": "2026-11-10 10:00:00"
}
```

---

# Controle de saldo

O sistema mantém a diferença entre:

```text
total da reserva
-
total pago
=
saldo restante
```

Exemplo:

```text
Total da reserva:  R$ 300,00
Pagamento 1:       R$ 100,00
Pagamento 2:       R$ 150,00
Saldo restante:     R$  50,00
```

Nesse cenário, um pagamento de R$ 50,00 é permitido.

Um pagamento de R$ 100,00 é rejeitado.

A regra não está apenas no Controller ou no Form Request.

Ela está no `PaymentService`, garantindo que a regra financeira seja respeitada independentemente de qual fluxo utilize o serviço.

---

# Concorrência nos pagamentos

O registro de pagamento utiliza:

* transação;
* bloqueio da reserva;
* cálculo do saldo dentro da operação protegida.

A reserva é bloqueada com `lockForUpdate()` antes da validação do saldo.

Isso reduz o risco de duas requisições simultâneas consumirem o mesmo saldo restante e ultrapassarem o valor total da reserva.

---

# Cupons e promoções

O sistema possui suporte a dois tipos de cupom:

```text
percentage
fixed
```

## Percentual

Aplica uma porcentagem sobre o subtotal.

O valor máximo permitido é 100%.

## Fixo

Aplica um valor absoluto de desconto.

## Validade

Um cupom pode possuir:

```text
starts_at
expires_at
```

Esses limites são opcionais.

Quando definidos, são considerados na validação do cupom.

## Status

Cupons com:

```text
active = false
```

não podem ser utilizados.

## Limite do desconto

O desconto nunca ultrapassa o subtotal.

Por exemplo:

```text
Subtotal: R$ 100,00
Cupom:    R$ 150,00
Desconto: R$ 100,00
```

O total nunca fica negativo.

## Persistência

Quando um cupom é utilizado, o sistema registra:

* reserva;
* cupom;
* desconto efetivamente aplicado.

Isso é armazenado em:

```text
reservation_coupons
```

O valor também é armazenado em:

```text
reservations.discount
```

---

# Regras de disponibilidade

A disponibilidade considera o período da reserva e a quantidade de unidades disponíveis.

A regra de conflito utiliza intervalo semiaberto:

```text
novo_check_in < reserva_existente_check_out

E

novo_check_out > reserva_existente_check_in
```

Isso significa:

```text
check-in = inclusivo
check-out = exclusivo
```

Portanto:

```text
Reserva A
10/01 → 13/01

Reserva B
13/01 → 16/01
```

não possuem conflito.

Essa regra permite que um quarto seja liberado no dia do check-out para uma nova hospedagem.

---

# Quantidade de unidades

Um quarto representa uma categoria/tipo de acomodação e possui um campo `quantity`.

Exemplo:

```text
Quarto Standard
quantity = 3
```

Se existirem duas reservas conflitantes:

```text
quantity = 3
reservas conflitantes = 2
disponibilidade = 1
```

Uma nova reserva ainda é permitida.

Quando a quantidade disponível chega a zero, novas reservas para o período são rejeitadas com:

```text
409 Conflict
```

Atualmente, cada reserva ocupa uma unidade da quantidade disponível.

---

# Controle de concorrência nas reservas

A criação da reserva ocorre dentro de uma transação.

O quarto é bloqueado durante a operação através de `lockForUpdate()`.

O objetivo é reduzir o risco de overbooking em requisições concorrentes.

O fluxo é:

```text
BEGIN TRANSACTION
       ↓
LOCK ROOM
       ↓
CHECK AVAILABILITY
       ↓
CALCULATE PRICE
       ↓
CREATE RESERVATION
       ↓
CREATE GUESTS
       ↓
CREATE DAILIES
       ↓
COMMIT
```

Caso uma etapa falhe, a transação é revertida.

---

# Datas e compatibilidade entre bancos

Durante o desenvolvimento foi identificado um detalhe importante relacionado ao uso do SQLite nos testes.

Embora a coluna da migration seja definida como `date`, o SQLite possui diferenças na forma como representa determinados valores em relação ao MySQL.

O Model `Reservation` utiliza um cast próprio chamado `DateOnly` para garantir que `check_in` e `check_out` sejam persistidos somente como:

```text
Y-m-d
```

Na leitura, o valor continua disponível como um objeto de data compatível com Carbon.

Essa decisão garante que a representação utilizada pelos testes e pelo MySQL seja consistente.

O problema foi particularmente importante para a regra de check-out exclusivo, pois comparações textuais no SQLite poderiam considerar:

```text
2027-01-13 00:00:00
```

diferente de:

```text
2027-01-13
```

O cast resolve a diferença na camada de persistência, sem alterar a regra de negócio de disponibilidade.

---

# Transações

A criação de uma reserva é atômica.

Ela envolve:

```text
Reservation
Guest
ReservationDaily
ReservationCoupon
```

Se uma dessas operações falhar, a transação é revertida.

Isso evita situações como:

```text
Reserva criada
Hóspede criado
Diária criada
Cupom falhou
```

com dados parcialmente persistidos.

O mesmo princípio é aplicado ao registro de pagamentos.

---

# Tratamento de erros

A API utiliza códigos HTTP de acordo com a situação.

| Código | Utilização                                       |
| ------ | ------------------------------------------------ |
| `200`  | Operação realizada com sucesso                   |
| `201`  | Recurso criado                                   |
| `204`  | Operação concluída sem conteúdo                  |
| `401`  | Usuário não autenticado                          |
| `404`  | Recurso não encontrado                           |
| `409`  | Conflito, como indisponibilidade                 |
| `422`  | Dados inválidos ou regra de negócio não atendida |

As respostas são disponibilizadas em JSON.

---

# Swagger / OpenAPI

A API possui documentação OpenAPI 3 utilizando L5-Swagger.

Após iniciar a aplicação:

```text
http://127.0.0.1:8000/api/documentation
```

Para regenerar:

```bash
php artisan l5-swagger:generate
```

A documentação inclui os principais endpoints de:

* autenticação;
* quartos;
* reservas;
* pagamentos.

Também apresenta:

* parâmetros;
* payloads;
* respostas;
* códigos HTTP;
* autenticação;
* schemas.

O Swagger UI pode ser utilizado para testar endpoints protegidos após informar o Bearer Token.

---

# Testes automatizados

O projeto utiliza PHPUnit através da estrutura de testes do Laravel.

A suíte atual possui:

```text
63 testes
226 assertions
0 falhas
```

Última validação realizada:

```text
63 passed (226 assertions)
```

---

# Cobertura dos testes

Os testes cobrem principalmente:

### Autenticação

* login válido;
* senha inválida;
* endpoint protegido sem autenticação;
* acesso autenticado.

### Reservas

* criação de reserva;
* cálculo financeiro;
* persistência;
* hotel e quarto incompatíveis;
* quarto já reservado;
* quarto inexistente;
* hotel inexistente;
* datas inválidas;
* check-in igual ao check-out;
* check-out anterior ao check-in;
* diárias ausentes;
* diárias duplicadas;
* diária no check-out;
* reservas consecutivas;
* múltiplas reservas conforme `quantity`;
* bloqueio quando a quantidade é excedida.

### Cupons

* cupom inexistente;
* cupom inativo;
* cupom ainda não iniciado;
* cupom expirado;
* desconto percentual;
* desconto fixo;
* arredondamento;
* limite do desconto;
* persistência do cupom aplicado;
* garantia de que valores financeiros enviados pelo cliente sejam ignorados.

### Pagamentos

* criação;
* autenticação;
* saldo;
* total pago;
* pagamentos sucessivos;
* pagamento exato do saldo;
* valor zero;
* valor negativo;
* pagamento acima do saldo;
* tentativa de acessar pagamento de outra reserva;
* exclusão;
* garantia de que pagamentos inválidos não sejam persistidos.

Os testes também ajudaram a identificar e corrigir a diferença de persistência de datas entre SQLite e MySQL.

---

# Estrutura do projeto

A estrutura principal segue:

```text
app/
├── Console/
│   └── Commands/
│       └── ImportHotelDataCommand.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │
│   ├── Requests/
│   │
│   └── Resources/
│
├── Models/
│
├── Services/
│   ├── Import/
│   │   ├── XmlImportService.php
│   │   ├── HotelImportService.php
│   │   ├── RoomImportService.php
│   │   ├── ReservationImportService.php
│   │   └── ImportErrorRecorder.php
│   │
│   └── Reservation/
│       ├── CreateReservationService.php
│       ├── AvailabilityService.php
│       ├── PricingService.php
│       ├── CouponService.php
│       ├── PaymentService.php
│       └── Exceptions / Value Objects
│
└── OpenApi.php
```

---

# Responsabilidades das principais camadas

| Camada               | Responsabilidade                   |
| -------------------- | ---------------------------------- |
| Controllers          | Coordenação das requisições HTTP   |
| Form Requests        | Validação da entrada               |
| Resources            | Representação JSON                 |
| Models               | Entidades e relacionamentos        |
| Import Services      | Processamento dos XMLs             |
| Reservation Services | Regras de reservas                 |
| PaymentService       | Regras financeiras de pagamentos   |
| Scheduler            | Agendamento da importação          |
| Commands             | Execução de processos via terminal |
| OpenAPI              | Documentação da API                |

---

# Principais decisões técnicas

## Services para regras de negócio

As principais regras não ficam diretamente nos Controllers.

Isso facilita:

* testes;
* reutilização;
* manutenção;
* evolução.

---

## Form Requests

Os Form Requests validam a entrada da aplicação.

Isso impede que Controllers sejam responsáveis por uma grande quantidade de regras de validação estrutural.

---

## API Resources

Os Resources controlam a representação pública dos dados.

Isso evita retornar diretamente qualquer informação existente nos Models.

---

## Transações

Operações que precisam ser atômicas utilizam transações.

Isso é aplicado principalmente em:

* criação de reservas;
* aplicação de cupons;
* registro de pagamentos.

---

## Controle de concorrência

Operações críticas utilizam `lockForUpdate()`.

Isso foi aplicado principalmente em:

* criação de reservas;
* registro de pagamentos.

O objetivo é proteger operações que dependem do estado atual do banco.

---

## Cálculo financeiro no backend

O cliente fornece os dados necessários para o cálculo, mas não controla o resultado financeiro.

O backend determina:

```text
subtotal
discount
fees
total
```

Isso impede manipulação de valores através da API.

---

## Valores monetários em centavos

Os cálculos financeiros utilizam inteiros representando centavos.

Essa estratégia reduz problemas de precisão relacionados a ponto flutuante.

---

## IDs externos

Os IDs dos XMLs são separados dos IDs internos do banco.

Isso permite:

* preservar a origem;
* manter os relacionamentos internos independentes;
* executar a importação novamente;
* evitar duplicações.

---

## Importação idempotente

A importação pode ser executada repetidamente.

Registros existentes são atualizados e registros novos são criados.

---

## Inconsistências auditáveis

Os dados inconsistentes não são simplesmente descartados.

Eles são registrados em `import_errors`.

Essa abordagem preserva a informação original e permite investigação posterior.

---

## Autenticação

A API utiliza Laravel Sanctum para proteger as operações que manipulam dados.

---

# Validação dos dados importados

Os arquivos fornecidos pelo desafio foram processados com sucesso.

Resultado esperado:

```text
Hotéis:          3
Quartos:         6
Reservas:        6
Hóspedes:        6
Diárias:         18
Pagamentos:      1
Import Errors:   1
```

A existência de um `ImportError` é intencional e corresponde à inconsistência presente no XML original.

---

# Comandos úteis

## Servidor local

```bash
php artisan serve
```

## Migrations

```bash
php artisan migrate
```

## Importação

```bash
php artisan hotel:import
```

## Importação com caminho customizado

```bash
php artisan hotel:import --path=/caminho/dos/xmls
```

## Scheduler

```bash
php artisan schedule:list
```

```bash
php artisan schedule:work
```

## Rotas

```bash
php artisan route:list
```

## Testes

```bash
php artisan test
```

## Swagger

```bash
php artisan l5-swagger:generate
```

## Docker

```bash
docker compose up -d --build
```

```bash
docker compose ps
```

```bash
docker compose exec app php artisan migrate
```

```bash
docker compose exec app php artisan hotel:import
```

```bash
docker compose exec app php artisan test
```

---

# Status do desafio

| Requisito / diferencial       | Status |
| ----------------------------- | ------ |
| Laravel / PHP                 | ✅      |
| Modelagem do banco            | ✅      |
| Importação XML                | ✅      |
| Importação idempotente        | ✅      |
| Tratamento de inconsistências | ✅      |
| Artisan Command               | ✅      |
| Scheduler / CRON              | ✅      |
| CRUD de quartos               | ✅      |
| API de reservas               | ✅      |
| JSON                          | ✅      |
| Disponibilidade               | ✅      |
| Quantidade de unidades        | ✅      |
| Cálculo financeiro            | ✅      |
| Cupons / promoções            | ✅      |
| Pagamentos                    | ✅      |
| Controle de saldo             | ✅      |
| Concorrência                  | ✅      |
| Sanctum                       | ✅      |
| Swagger / OpenAPI 3           | ✅      |
| PHPUnit                       | ✅      |
| Docker                        | ✅      |
| REST / HTTP verbs             | ✅      |
| Segurança de endpoints        | ✅      |
| Tratamento de erros           | ✅      |
| Documentação                  | ✅      |

---

# Possíveis evoluções

O projeto atual foi mantido dentro do escopo definido para o desafio.

A arquitetura, entretanto, permite evoluções futuras.

Entre elas:

* integração com gateways de pagamento;
* estornos e reembolsos;
* pagamentos parcelados;
* conciliação de pagamentos;
* gestão de usuários;
* perfis e permissões;
* logs de auditoria;
* observabilidade;
* limites de utilização de cupons;
* campanhas promocionais mais avançadas;
* taxas e juros;
* serviços adicionais;
* gerenciamento completo de hotéis;
* consulta avançada de disponibilidade;
* frontend consumidor da API;
* notificações;
* monitoramento operacional.

Essas funcionalidades não fazem parte do escopo atualmente implementado.

---

# Considerações finais

O projeto foi desenvolvido com o objetivo de demonstrar não apenas a implementação dos endpoints solicitados, mas também a capacidade de estruturar uma aplicação backend considerando regras de negócio, integridade dos dados e evolução futura.

Durante o desenvolvimento foram priorizados:

* separação de responsabilidades;
* arquitetura orientada a Services;
* validação de dados;
* integridade referencial;
* importação idempotente;
* tratamento explícito de inconsistências;
* transações;
* controle de concorrência;
* segurança dos endpoints;
* cálculo financeiro no backend;
* controle de pagamentos;
* cupons;
* testes automatizados;
* documentação OpenAPI;
* containerização com Docker.

Um dos pontos importantes da implementação foi tratar os dados dos XMLs como uma fonte externa que pode conter inconsistências. Em vez de alterar silenciosamente essas informações, o sistema preserva os dados e registra os problemas encontrados para auditoria.

Outro ponto importante foi a preocupação com concorrência. Tanto reservas quanto pagamentos possuem operações críticas protegidas por transações e bloqueio de registros, reduzindo riscos de overbooking e de pagamentos superiores ao saldo.

A camada financeira também foi mantida no backend. O cliente fornece os dados necessários para a operação, mas os valores de subtotal, desconto, taxas e total são calculados pelo servidor.

A aplicação possui atualmente uma suíte automatizada com:

```text
63 testes
226 assertions
0 falhas
```

Além disso, o projeto pode ser executado através de Docker, possui documentação OpenAPI 3 e possui um fluxo completo de importação automatizada através de Artisan Command + Laravel Scheduler + CRON.

O resultado é uma base de backend organizada, testada e preparada para evolução, mantendo o escopo atual focado nos requisitos do desafio técnico.
