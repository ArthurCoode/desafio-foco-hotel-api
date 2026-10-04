# Foco Hotel API

## Sobre o projeto

O **Foco Hotel API** é uma API REST desenvolvida em Laravel para o gerenciamento de hotéis, quartos, reservas e pagamentos.

O projeto inclui:

* importação de dados a partir de arquivos XML;
* importação idempotente, evitando duplicidade de registros;
* registro de inconsistências encontradas nos dados de origem;
* regras de disponibilidade de quartos por período;
* controle de quantidade de unidades disponíveis por quarto;
* cálculo financeiro das reservas (subtotal, desconto, taxas e total);
* aplicação de cupons de desconto;
* gerenciamento de pagamentos vinculados às reservas;
* controle de saldo e total pago das reservas;
* prevenção de pagamentos superiores ao saldo restante;
* autenticação da API utilizando Laravel Sanctum;
* documentação da API utilizando Swagger/OpenAPI 3;
* testes automatizados com PHPUnit;
* respostas padronizadas em JSON.

O projeto foi desenvolvido como **desafio técnico**, com o objetivo de demonstrar organização de código, separação de responsabilidades, tratamento de inconsistências de dados, segurança de endpoints e cobertura de regras de negócio por testes automatizados.

---

## Tecnologias

| Tecnologia      | Uso no projeto                                      |
| --------------- | --------------------------------------------------- |
| PHP 8.5         | Linguagem da aplicação                              |
| Laravel 13      | Framework da aplicação e API REST                   |
| MySQL 8         | Banco de dados relacional                           |
| Composer        | Gerenciamento de dependências PHP                   |
| PHPUnit         | Testes automatizados                                |
| Laravel Sanctum | Autenticação baseada em Bearer Token                |
| L5-Swagger      | Geração da documentação OpenAPI 3                   |
| Swagger UI      | Interface para visualização e teste da documentação |
| Git             | Controle de versão                                  |

---

## Requisitos

* PHP 8.5, com as extensões exigidas pelo Laravel, incluindo `pdo_mysql`;
* Composer;
* MySQL 8;
* Git.

---

## Instalação

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>

cd <diretorio-do-projeto>
```

### 2. Instale as dependências

```bash
composer install
```

### 3. Configure o arquivo de ambiente

Copie o arquivo `.env.example` para `.env`:

```bash
cp .env.example .env
```

No Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Gere a chave da aplicação:

```bash
php artisan key:generate
```

### 4. Configure o banco de dados

Configure a conexão com o MySQL no arquivo `.env`.

Exemplo:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=foco_hotel
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 5. Crie o banco de dados

Crie o banco com o mesmo nome definido em `DB_DATABASE`.

Exemplo:

```sql
CREATE DATABASE foco_hotel
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 6. Execute as migrations

```bash
php artisan migrate
```

As migrations criam as tabelas da aplicação, incluindo as estruturas necessárias para autenticação com Laravel Sanctum, reservas, cupons e pagamentos.

### 7. Importe os dados dos XMLs

Com os arquivos XML no diretório padrão:

```bash
php artisan hotel:import
```

### 8. Inicie o servidor local

```bash
php artisan serve
```

Por padrão, a aplicação ficará disponível em:

```text
http://127.0.0.1:8000
```

---

## Configuração do ambiente

As principais variáveis do `.env` relacionadas ao banco de dados são:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=foco_hotel
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

| Variável        | Descrição                            |
| --------------- | ------------------------------------ |
| `DB_CONNECTION` | Driver de conexão (`mysql`)          |
| `DB_HOST`       | Host do servidor MySQL               |
| `DB_PORT`       | Porta do MySQL                       |
| `DB_DATABASE`   | Nome do banco utilizado pelo projeto |
| `DB_USERNAME`   | Usuário com acesso ao banco          |
| `DB_PASSWORD`   | Senha do usuário                     |

A porta `3307` é a utilizada no ambiente de desenvolvimento deste projeto, mas pode variar conforme a instalação local. A porta padrão do MySQL é `3306`.

Os valores acima são exemplos. O arquivo `.env` contém configurações locais e credenciais e não deve ser versionado.

---

# Banco de dados

| Tabela                   | Descrição                                                                         |
| ------------------------ | --------------------------------------------------------------------------------- |
| `hotels`                 | Hotéis cadastrados                                                                |
| `rooms`                  | Quartos de cada hotel, incluindo a quantidade (`quantity`) disponível             |
| `reservations`           | Reservas, com período, status e valores (`subtotal`, `discount`, `fees`, `total`) |
| `guests`                 | Hóspedes vinculados a uma reserva                                                 |
| `reservation_dailies`    | Diárias de uma reserva, contendo data e valor                                     |
| `payments`               | Pagamentos vinculados às reservas, com forma, valor e data de pagamento           |
| `coupons`                | Cupons de desconto                                                                |
| `reservation_coupons`    | Cupons aplicados às reservas e respectivos descontos                              |
| `import_errors`          | Inconsistências encontradas durante a importação dos XMLs                         |
| `users`                  | Usuários utilizados pela autenticação da API                                      |
| `personal_access_tokens` | Tokens gerenciados pelo Laravel Sanctum                                           |

### Identificadores externos (`external_id`)

Os IDs presentes nos arquivos XML são tratados como **identificadores externos** e armazenados na coluna `external_id`.

Eles **não substituem** os IDs internos, que continuam sendo as chaves primárias do banco.

Os relacionamentos entre as tabelas utilizam os IDs internos. O `external_id` é utilizado principalmente para localizar registros provenientes dos XMLs durante novas importações.

Essa separação permite que a importação seja executada novamente sem depender dos IDs internos do banco.

---

# Importação dos XMLs

O fluxo de importação é:

```text
XML
 ↓
XmlImportService
 ↓
Serviços específicos de importação
 ↓
Models / Eloquent
 ↓
Banco de dados
```

O comando Artisan inicia o processo. A leitura, validação e persistência dos dados ficam nos serviços de `app/Services/Import`.

A estrutura principal é:

```text
app/Services/Import/

├── XmlImportService.php
├── HotelImportService.php
├── RoomImportService.php
└── ReservationImportService.php
```

## Comando

```bash
php artisan hotel:import
```

O comando utiliza `storage/app/imports` como diretório padrão dos XMLs.

É possível informar outro diretório utilizando `--path`:

```bash
php artisan hotel:import --path=/caminho/dos/xmls
```

Os arquivos esperados são:

```text
hotels.xml
rooms.xml
reserves.xml
```

## Idempotência

A importação utiliza os identificadores externos e operações como `updateOrCreate` para localizar registros já existentes.

Dessa forma, executar a importação novamente não duplica hotéis, quartos ou reservas.

Registros existentes são atualizados e registros novos são criados.

## Tratamento de inconsistências

Inconsistências nos dados de origem **não são corrigidas silenciosamente**.

Quando uma inconsistência de negócio é identificada, ela é registrada na tabela `import_errors`, preservando os dados originais importados.

### Exemplo

A reserva 6 possui:

```text
Check-in:  2022-10-01
Check-out: 2022-10-04
```

Considerando check-in inclusivo e check-out exclusivo, o período corresponde às noites:

```text
2022-10-01
2022-10-02
2022-10-03
```

Entretanto, o XML possui também uma diária em:

```text
2022-12-03
```

Essa diária está fora do período da reserva.

O sistema preserva a informação importada e registra a inconsistência em `import_errors` com o tipo:

```text
daily_outside_stay_period
```

---

# Scheduler e CRON

O Laravel Scheduler está configurado em `routes/console.php` para executar a importação uma vez por hora:

```php
Schedule::command('hotel:import')
    ->hourly()
    ->withoutOverlapping();
```

### `hourly()`

Executa o comando no minuto 0 de cada hora.

### `withoutOverlapping()`

Evita que duas execuções da mesma tarefa ocorram simultaneamente.

## CRON, Scheduler e comando

| Componente        | Papel                                               |
| ----------------- | --------------------------------------------------- |
| CRON do sistema   | Executa o Scheduler do Laravel periodicamente       |
| Laravel Scheduler | Verifica quais tarefas estão no horário de execução |
| `hotel:import`    | Executa efetivamente a importação dos XMLs          |

Em produção, o CRON deve executar o Scheduler do Laravel a cada minuto:

```bash
php artisan schedule:run
```

Exemplo:

```text
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para listar os agendamentos:

```bash
php artisan schedule:list
```

Em desenvolvimento local, também é possível manter o Scheduler executando em primeiro plano:

```bash
php artisan schedule:work
```

---

# API REST

A API utiliza respostas em JSON e possui o prefixo:

```text
/api
```

As rotas podem ser verificadas com:

```bash
php artisan route:list
```

A rota de login é pública. As demais rotas relacionadas aos dados protegidos exigem autenticação através de Bearer Token.

---

# Autenticação

A autenticação da API é realizada utilizando **Laravel Sanctum**.

## Login

```http
POST /api/login
```

Exemplo:

```json
{
    "email": "teste@foco.com",
    "password": "12345678"
}
```

Em caso de sucesso, a API retorna um token:

```json
{
    "message": "Login realizado com sucesso.",
    "token": "...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "Teste",
        "email": "teste@foco.com"
    }
}
```

O token deve ser enviado nas requisições protegidas:

```http
Authorization: Bearer {token}
Accept: application/json
```

## Rotas protegidas

As seguintes rotas exigem autenticação:

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

Sem um token válido, a API retorna:

```http
401 Unauthorized
```

com resposta JSON semelhante a:

```json
{
    "message": "Unauthenticated."
}
```

### Segurança

A autenticação é aplicada na API através do middleware:

```text
auth:sanctum
```

As regras de negócio e os dados continuam sendo protegidos no backend, independentemente da interface que consuma a API.

---

# Swagger / OpenAPI

A API possui documentação utilizando **OpenAPI 3**, gerada pelo pacote L5-Swagger.

Após iniciar o servidor, a documentação pode ser acessada em:

```text
http://127.0.0.1:8000/api/documentation
```

Para regenerar a documentação:

```bash
php artisan l5-swagger:generate
```

A documentação contempla os principais endpoints da API, incluindo:

* autenticação;
* quartos;
* reservas;
* pagamentos;
* parâmetros;
* respostas;
* requisitos de autenticação.

O Swagger também pode ser utilizado para testar os endpoints protegidos informando o Bearer Token.

---

# API de quartos

A API de quartos utiliza `apiResource` e segue os verbos HTTP REST.

| Método   | Endpoint            | Finalidade          |
| -------- | ------------------- | ------------------- |
| `GET`    | `/api/rooms`        | Listar quartos      |
| `POST`   | `/api/rooms`        | Criar quarto        |
| `GET`    | `/api/rooms/{room}` | Exibir um quarto    |
| `PUT`    | `/api/rooms/{room}` | Atualizar um quarto |
| `PATCH`  | `/api/rooms/{room}` | Atualizar um quarto |
| `DELETE` | `/api/rooms/{room}` | Remover um quarto   |

A listagem utiliza paginação do Laravel.

Os parâmetros de criação e atualização são validados através dos Form Requests correspondentes.

### Campos do quarto

| Campo         | Descrição                          |
| ------------- | ---------------------------------- |
| `hotel_id`    | ID interno do hotel                |
| `external_id` | Identificador externo do quarto    |
| `name`        | Nome do quarto                     |
| `quantity`    | Quantidade de unidades disponíveis |

A combinação de `hotel_id` e `external_id` deve ser única.

### Observação sobre PUT e PATCH

O endpoint `PATCH` está disponível para seguir a interface REST da API, porém atualmente utiliza o mesmo `UpdateRoomRequest` do `PUT`.

Portanto, **a implementação atual não trata PATCH como atualização parcialmente opcional**. Os campos definidos como obrigatórios na validação continuam sendo exigidos.

---

# API de reservas

## Criar reserva

```http
POST /api/reservations
```

Campos aceitos no corpo da requisição:

| Campo              | Obrigatório | Regras                                                          |
| ------------------ | ----------- | --------------------------------------------------------------- |
| `hotel_id`         | Sim         | Inteiro; deve existir em `hotels`                               |
| `room_id`          | Sim         | Inteiro; deve existir em `rooms` e pertencer ao hotel informado |
| `check_in`         | Sim         | Data no formato `Y-m-d`                                         |
| `check_out`        | Sim         | Data no formato `Y-m-d`, posterior a `check_in`                 |
| `guests`           | Sim         | Array com ao menos um hóspede                                   |
| `guests.*.name`    | Sim         | Nome com até 255 caracteres                                     |
| `guests.*.phone`   | Não         | Telefone com até 30 caracteres                                  |
| `dailies`          | Sim         | Array de diárias                                                |
| `dailies.*.date`   | Sim         | Data no formato `Y-m-d`                                         |
| `dailies.*.amount` | Sim         | Valor numérico não negativo                                     |
| `coupon_code`      | Não         | Código do cupom, com até 50 caracteres                          |

As diárias devem corresponder exatamente às noites da reserva, considerando:

```text
check-in inclusivo
check-out exclusivo
```

Não são permitidas datas duplicadas.

### Exemplo

```json
{
    "hotel_id": 1,
    "room_id": 1,
    "check_in": "2026-11-10",
    "check_out": "2026-11-13",
    "guests": [
        {
            "name": "Maria Silva",
            "phone": "5571999999999"
        }
    ],
    "dailies": [
        {
            "date": "2026-11-10",
            "amount": "200.00"
        },
        {
            "date": "2026-11-11",
            "amount": "200.00"
        },
        {
            "date": "2026-11-12",
            "amount": "200.00"
        }
    ],
    "coupon_code": "PROMO"
}
```

### Valores financeiros

Os valores:

```text
subtotal
discount
fees
total
```

**não são recebidos como fonte de verdade do cliente**.

O backend calcula esses valores com base nas diárias, cupom e regras de negócio.

Isso evita que um cliente da API envie, por exemplo:

```json
{
    "total": "1.00"
}
```

e consiga alterar o valor real da reserva.

### Respostas

| Código | Situação                                         |
| ------ | ------------------------------------------------ |
| `201`  | Reserva criada com sucesso                       |
| `401`  | Usuário não autenticado                          |
| `422`  | Dados inválidos ou regra de negócio não atendida |

Quando o quarto não possui disponibilidade para o período informado, a API retorna:

```http
409 Conflict
```

---

# API de pagamentos

O sistema permite registrar e gerenciar pagamentos vinculados às reservas.

O gerenciamento atual é feito no próprio sistema e **não representa uma integração com gateway de pagamento**.

## Endpoints

| Método   | Endpoint                                             | Finalidade          |
| -------- | ---------------------------------------------------- | ------------------- |
| `GET`    | `/api/reservations/{reservation}/payments`           | Listar pagamentos   |
| `POST`   | `/api/reservations/{reservation}/payments`           | Registrar pagamento |
| `GET`    | `/api/reservations/{reservation}/payments/{payment}` | Consultar pagamento |
| `DELETE` | `/api/reservations/{reservation}/payments/{payment}` | Excluir pagamento   |

Todos os endpoints exigem autenticação através do Laravel Sanctum.

## Registrar pagamento

```http
POST /api/reservations/{reservation}/payments
```

Campos:

| Campo     | Obrigatório | Regras                                         |
| --------- | ----------- | ---------------------------------------------- |
| `method`  | Sim         | String com até 50 caracteres                   |
| `amount`  | Sim         | Valor maior que zero, com até 2 casas decimais |
| `paid_at` | Não         | Data válida                                    |

Exemplo:

```json
{
    "method": "credit_card",
    "amount": 100.00,
    "paid_at": "2026-11-10 10:00:00"
}
```

Em caso de sucesso:

```http
201 Created
```

A resposta contém:

```json
{
    "message": "Pagamento registrado com sucesso.",
    "payment": {
        "id": 1,
        "reservation_id": 1,
        "method": "credit_card",
        "amount": "100.00",
        "paid_at": "2026-11-10T10:00:00.000000Z",
        "created_at": "2026-11-10T10:00:00.000000Z"
    },
    "total_paid": "100.00",
    "remaining_balance": "200.00"
}
```

## Listar pagamentos

```http
GET /api/reservations/{reservation}/payments
```

A resposta apresenta:

* pagamentos registrados;
* total já pago;
* saldo restante.

Exemplo:

```json
{
    "payments": [
        {
            "id": 1,
            "reservation_id": 1,
            "method": "credit_card",
            "amount": "100.00",
            "paid_at": "2026-11-10T10:00:00.000000Z",
            "created_at": "2026-11-10T10:00:00.000000Z"
        }
    ],
    "total_paid": "100.00",
    "remaining_balance": "200.00"
}
```

## Consultar pagamento

```http
GET /api/reservations/{reservation}/payments/{payment}
```

O pagamento precisa pertencer à reserva informada.

Caso contrário, a API retorna:

```http
404 Not Found
```

```json
{
    "message": "Pagamento não encontrado."
}
```

## Excluir pagamento

```http
DELETE /api/reservations/{reservation}/payments/{payment}
```

Quando o pagamento pertence à reserva, a API retorna:

```http
204 No Content
```

Caso o pagamento não pertença à reserva informada:

```http
404 Not Found
```

## Regra de saldo

O total pago nunca pode ultrapassar o total da reserva.

Exemplo:

```text
Total da reserva: 300.00
Pagamento 1:      100.00
Pagamento 2:      150.00
Saldo restante:    50.00
```

Um novo pagamento de `50.00` é permitido.

Um novo pagamento de `100.00` é rejeitado:

```http
422 Unprocessable Entity
```

Exemplo:

```json
{
    "message": "O pagamento de 100.00 excede o saldo restante da reserva (50.00)."
}
```

A regra é aplicada no `PaymentService`, e não apenas na validação HTTP.

Isso garante que a regra financeira também seja respeitada caso o serviço seja utilizado por outro fluxo da aplicação.

## Concorrência

O registro do pagamento utiliza uma transação e `lockForUpdate()` na reserva.

Isso reduz o risco de duas operações concorrentes utilizarem simultaneamente o mesmo saldo restante e registrarem pagamentos que ultrapassem o total da reserva.

Os valores utilizados nas comparações financeiras são convertidos para centavos inteiros.

---

# Regras de negócio

## Disponibilidade

Uma reserva existente entra em conflito com uma nova reserva quando:

```text
novo_check_in < reserva_existente_check_out

E

novo_check_out > reserva_existente_check_in
```

O check-in é inclusivo e o check-out é exclusivo.

Assim, uma reserva que termina no mesmo dia em que outra começa não gera conflito.

Atualmente, apenas reservas com status:

```text
confirmed
```

são consideradas ocupantes.

A disponibilidade também considera `quantity`.

Por exemplo:

```text
quantity = 3

reservas conflitantes = 2

disponibilidade = 1
```

O sistema permite novas reservas enquanto a quantidade de reservas conflitantes for menor que a quantidade disponível.

Durante a criação da reserva, o registro do quarto é bloqueado com `lockForUpdate()` dentro de uma transação para reduzir o risco de overbooking em requisições concorrentes.

---

## Cálculo financeiro

### Subtotal

O subtotal é a soma dos valores das diárias:

```text
subtotal = soma(dailies.amount)
```

### Desconto

O desconto é calculado pelo `CouponService`.

Sem cupom:

```text
discount = 0.00
```

### Taxas

O campo `fees` existe para suportar taxas, juros ou serviços adicionais.

Atualmente:

```text
fees = 0.00
```

As regras específicas de taxas e juros ainda não estão implementadas.

### Total

```text
total = subtotal - discount + fees
```

Todos os valores são calculados no backend.

Durante os cálculos monetários, os valores são convertidos para centavos inteiros, evitando problemas de precisão associados à aritmética de ponto flutuante.

Quando uma terceira casa decimal está presente, o arredondamento utilizado é **half-up**.

---

# Cupons

O sistema suporta dois tipos:

```text
percentage
fixed
```

## Percentual

Representa um percentual aplicado sobre o subtotal.

O intervalo permitido é de:

```text
0% a 100%
```

## Valor fixo

Representa um desconto fixo em reais.

## Validade

Um cupom pode possuir:

```text
starts_at
expires_at
```

Ambos são opcionais.

Quando informados, os limites são inclusivos.

## Status

Cupons com:

```text
active = false
```

são rejeitados.

## Limite do desconto

O desconto nunca pode ultrapassar o subtotal.

Portanto:

```text
total >= 0
```

## Registro

Quando um cupom é aplicado, o sistema registra:

* o cupom utilizado;
* a reserva;
* o desconto efetivamente aplicado.

Essas informações ficam em:

```text
reservation_coupons
```

O desconto também é persistido em:

```text
reservations.discount
```

## Falhas

Cupom inexistente, inativo, ainda não iniciado, expirado ou inválido interrompe a criação da reserva.

Como a criação ocorre dentro de uma transação, a operação é revertida.

O projeto não implementa atualmente limite de quantidade de utilizações por cupom ou outras restrições além das descritas acima.

---

# Transações

A criação de uma reserva é executada dentro de uma transação de banco de dados.

A operação envolve:

```text
Reserva
 ↓
Hóspedes
 ↓
Diárias
 ↓
Cupom aplicado
```

Caso alguma etapa falhe, a transação é revertida e os registros não são persistidos parcialmente.

O registro de pagamentos também utiliza transação, garantindo que o pagamento seja criado somente após a validação do saldo disponível.

---

# Tratamento de erros

A API utiliza códigos HTTP apropriados para representar diferentes situações.

| Código | Significado                                      |
| ------ | ------------------------------------------------ |
| `200`  | Requisição processada com sucesso                |
| `201`  | Recurso criado                                   |
| `204`  | Operação realizada sem conteúdo de resposta      |
| `401`  | Não autenticado                                  |
| `404`  | Recurso não encontrado                           |
| `409`  | Conflito, como quarto indisponível               |
| `422`  | Dados inválidos ou regra de negócio não atendida |

Erros de validação utilizam o formato JSON padrão do Laravel, contendo uma mensagem e, quando aplicável, erros associados aos campos.

---

# Testes

O projeto possui testes automatizados com PHPUnit.

A suíte atual possui:

```text
55 testes
203 assertions
```

Os testes cobrem principalmente:

* autenticação;
* criação e validação de reservas;
* disponibilidade;
* quantidade de quartos;
* cálculo financeiro;
* cupons;
* pagamentos;
* validações da API;
* endpoints de quartos;
* integração de cupons com reservas;
* regras de negócio da API;
* segurança dos endpoints.

## Testes de pagamentos

Os testes de pagamentos cobrem:

* autenticação dos endpoints;
* registro de pagamento;
* cálculo do total pago;
* cálculo do saldo restante;
* pagamento com valor zero;
* pagamento com valor negativo;
* pagamento acima do saldo;
* múltiplos pagamentos;
* pagamento até atingir exatamente o total;
* consulta de pagamento;
* tentativa de acessar pagamento de outra reserva;
* exclusão de pagamento;
* garantia de que pagamentos inválidos não sejam persistidos.

Para executar a suíte:

```bash
php artisan test
```

---

# Estrutura do projeto

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
│   │   └── ReservationImportService.php
│   │
│   ├── Reservation/
│   │   ├── AvailabilityService.php
│   │   ├── PricingService.php
│   │   ├── CouponService.php
│   │   ├── CreateReservationService.php
│   │   ├── PaymentService.php
│   │   ├── CouponNotFoundException.php
│   │   ├── InvalidCouponException.php
│   │   ├── AppliedCoupon.php
│   │   └── PaymentExceedsReservationTotalException.php
│   │
│   └── Room/
│
└── OpenApi.php
```

| Camada                 | Responsabilidade                                                  |
| ---------------------- | ----------------------------------------------------------------- |
| `Console/Commands`     | Comandos Artisan, como `hotel:import`                             |
| `Http/Controllers`     | Recebem requisições e coordenam a aplicação                       |
| `Http/Requests`        | Validação da estrutura dos dados de entrada                       |
| `Http/Resources`       | Padronização das respostas JSON                                   |
| `Models`               | Entidades Eloquent e relacionamentos                              |
| `Services/Import`      | Leitura, validação e persistência dos XMLs                        |
| `Services/Reservation` | Disponibilidade, preços, cupons, criação de reservas e pagamentos |
| `Services/Room`        | Regras relacionadas a quartos                                     |
| `OpenApi.php`          | Configuração principal da documentação OpenAPI                    |

---

# Decisões técnicas

### Services para regras de negócio

As regras de negócio foram separadas dos Controllers em Services específicos.

Isso mantém os Controllers menores e facilita testes e reutilização.

### Form Requests

Os Form Requests são responsáveis pela validação estrutural dos dados recebidos.

Por exemplo, o `StoreReservationRequest` verifica a estrutura e os formatos dos dados da reserva, enquanto a validade do cupom é responsabilidade do `CouponService`.

### API Resources

Os API Resources padronizam as respostas JSON retornadas pela API.

### Transactions

A criação de reservas utiliza transações para garantir que reserva, hóspedes, diárias e cupom aplicado sejam persistidos de forma atômica.

O registro de pagamentos também utiliza transações para preservar a integridade financeira da operação.

### Controle de concorrência

O quarto utilizado na criação da reserva é bloqueado com `lockForUpdate()` dentro da transação, reduzindo o risco de duas requisições concorrentes ultrapassarem a quantidade disponível.

O registro de pagamentos também utiliza `lockForUpdate()` na reserva antes de verificar o saldo restante.

### Cálculo financeiro no backend

Os valores financeiros não são confiados ao cliente.

O backend calcula:

```text
subtotal
discount
fees
total
```

a partir dos dados válidos recebidos e das regras de negócio.

### Valores monetários em centavos

Os cálculos financeiros utilizam centavos inteiros para evitar problemas de precisão de ponto flutuante.

Essa abordagem também é utilizada no controle de pagamentos.

### IDs externos separados dos internos

Os identificadores dos XMLs são armazenados como `external_id`, mantendo os IDs internos do banco independentes da origem dos dados.

### Importação idempotente

A importação utiliza os identificadores externos para atualizar registros existentes e criar apenas registros novos.

### Registro de inconsistências

Problemas encontrados nos XMLs são registrados em `import_errors` sem alterar ou descartar silenciosamente os dados de origem.

### Autenticação com Sanctum

As rotas da API que manipulam dados protegidos utilizam Laravel Sanctum e exigem autenticação através de Bearer Token.

### Gerenciamento de pagamentos

As regras de pagamento foram isoladas no `PaymentService`, mantendo a lógica financeira fora do Controller.

O serviço controla:

* registro dos pagamentos;
* total pago;
* saldo restante;
* limite máximo de pagamento;
* operações concorrentes.

O pagamento é registrado dentro de uma transação e a reserva é bloqueada com `lockForUpdate()` durante a verificação do saldo.

### Documentação OpenAPI

A API possui documentação OpenAPI 3 gerada através do L5-Swagger, permitindo visualizar e testar os endpoints documentados.

---

# Comandos úteis

### Servidor

```bash
php artisan serve
```

### Migrations

```bash
php artisan migrate
```

### Importação

```bash
php artisan hotel:import
```

### Scheduler

```bash
php artisan schedule:list
```

```bash
php artisan schedule:work
```

### Rotas

```bash
php artisan route:list
```

### Testes

```bash
php artisan test
```

### Swagger

```bash
php artisan l5-swagger:generate
```

Documentação:

```text
http://127.0.0.1:8000/api/documentation
```

---

# Considerações finais

O projeto foi desenvolvido com foco em uma API REST organizada, segura e preparada para evolução.

Durante a implementação, foram priorizados:

* separação de responsabilidades;
* regras de negócio desacopladas dos Controllers;
* validação de dados;
* integridade das informações;
* controle de concorrência;
* segurança dos endpoints;
* cálculos financeiros no backend;
* gerenciamento de pagamentos;
* importação idempotente;
* tratamento explícito de inconsistências;
* testes automatizados;
* documentação da API.

O domínio também foi estruturado de forma a permitir futuras evoluções, como:

* integração com gateways de pagamento;
* estornos e reembolsos;
* pagamentos parcelados;
* status e conciliação de pagamentos;
* gestão de usuários e permissões;
* logs e observabilidade mais estruturados;
* regras promocionais mais avançadas;
* limites de utilização de cupons;
* taxas, juros e serviços adicionais;
* endpoints adicionais para gerenciamento de hotéis;
* frontend integrado à API;
* melhorias de monitoramento e operação em ambiente de produção.

Essas funcionalidades não fazem parte do escopo atualmente implementado e podem ser adicionadas conforme novas necessidades do sistema.

O backend atual representa a base principal do projeto, com as principais regras de negócio, autenticação, persistência, importação, gerenciamento de pagamentos, documentação e testes estruturados para permitir sua evolução.
