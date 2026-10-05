# Foco Hotel API

API REST desenvolvida em **Laravel** para gerenciamento de hotéis, quartos, reservas e pagamentos, com importação de dados via XML, autenticação, controle de disponibilidade, cupons e documentação OpenAPI.

O projeto foi desenvolvido como desafio técnico, priorizando **organização, segurança, consistência dos dados, testes automatizados e boas práticas de desenvolvimento**.

---

## 🚀 Tecnologias

* PHP 8.5
* Laravel 13
* MySQL 8
* Laravel Sanctum
* PHPUnit
* Swagger / OpenAPI 3
* Docker + Docker Compose
* Git

---

## 📋 Requisitos do desafio

### Implementados

* [x] Modelagem do banco de dados
* [x] Importação dos XMLs
* [x] Importação executável via Artisan
* [x] Agendamento via Laravel Scheduler
* [x] CRUD de quartos
* [x] Criação de reservas
* [x] Controle de disponibilidade
* [x] Cálculo do valor da reserva no backend
* [x] Cupons e descontos
* [x] Gerenciamento de pagamentos
* [x] Autenticação
* [x] Respostas JSON
* [x] Swagger / OpenAPI 3
* [x] Testes automatizados
* [x] Docker

---

## ⚙️ Como executar

### Com Docker

Configure as variáveis de banco no `.env` e execute:

```bash
docker compose up -d --build
```

Depois:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan hotel:import
```

Para executar os testes:

```bash
docker compose exec app php artisan test
```

A aplicação ficará disponível em:

```text
http://127.0.0.1:8000
```

Swagger:

```text
http://127.0.0.1:8000/api/documentation
```

### Sem Docker

Instale as dependências:

```bash
composer install
```

Configure o `.env`, execute as migrations:

```bash
php artisan migrate
```

E importe os dados:

```bash
php artisan hotel:import
```

---

## 🔐 Autenticação

A API utiliza **Laravel Sanctum**.

### Usuário de teste

```text
E-mail: teste@foco.com
Senha: 12345678
```

O usuário existe apenas para facilitar a execução e avaliação do projeto.

Após realizar o login, utilize o token retornado como:

```text
Authorization: Bearer {token}
```

As rotas protegidas exigem autenticação.

---

## 📥 Importação dos XMLs

O projeto importa três arquivos:

```text
storage/app/imports/
├── hotels.xml
├── rooms.xml
└── reserves.xml
```

Comando:

```bash
php artisan hotel:import
```

Também é possível informar outro diretório:

```bash
php artisan hotel:import --path=/caminho/dos/xmls
```

A importação é **idempotente**, utilizando os identificadores externos dos XMLs para evitar duplicidades.

As entidades são importadas na ordem:

```text
Hotéis → Quartos → Reservas
```

Inconsistências encontradas durante a importação são registradas em `import_errors` sem descartar os dados originais.

### Resultado dos XMLs fornecidos

* 3 hotéis
* 6 quartos
* 6 reservas
* 6 hóspedes
* 18 diárias
* 1 pagamento
* 1 inconsistência registrada

A inconsistência existente no XML é preservada e registrada para auditoria.

---

## ⏰ Scheduler / CRON

A importação está configurada no Laravel Scheduler:

```text
hotel:import → execução horária
```

Foi utilizado `withoutOverlapping()` para evitar execuções simultâneas.

Em produção, o Scheduler pode ser executado pelo CRON através de:

```bash
php artisan schedule:run
```

---

## 🏨 Quartos

CRUD completo através de:

```text
GET    /api/rooms
POST   /api/rooms
GET    /api/rooms/{room}
PUT    /api/rooms/{room}
PATCH  /api/rooms/{room}
DELETE /api/rooms/{room}
```

Cada quarto possui uma `quantity`, permitindo representar múltiplas unidades do mesmo tipo de acomodação.

---

## 🛎️ Reservas

Criação através de:

```text
POST /api/reservations
```

O backend é responsável por calcular:

```text
Subtotal
- Desconto
+ Taxas
= Total
```

O valor enviado pelo cliente não é utilizado para definir o total da reserva.

### Disponibilidade

A disponibilidade considera:

* quarto;
* período da reserva;
* quantidade disponível;
* reservas com status `confirmed`.

O período utiliza:

```text
check-in inclusivo
check-out exclusivo
```

Assim, uma reserva que termina no dia 10 não impede outra que começa no dia 10.

A criação da reserva utiliza transação e `lockForUpdate()` para evitar conflitos em reservas concorrentes.

---

## 🎟️ Cupons

São suportados dois tipos:

* percentual;
* valor fixo.

O backend valida:

* existência;
* status ativo;
* período de validade;
* valor do desconto.

O desconto nunca pode ultrapassar o subtotal da reserva.

O cupom aplicado também é registrado no banco juntamente com o valor efetivamente descontado.

---

## 💳 Pagamentos

A API permite:

```text
GET    /api/reservations/{reservation}/payments
POST   /api/reservations/{reservation}/payments
GET    /api/reservations/{reservation}/payments/{payment}
DELETE /api/reservations/{reservation}/payments/{payment}
```

O sistema controla o saldo restante da reserva e impede pagamentos superiores ao valor devido.

O registro do pagamento utiliza transação e bloqueio da reserva para proteger contra concorrência.

---

## 🧪 Testes

O projeto possui testes automatizados utilizando PHPUnit e Laravel.

Cobertura inclui:

* autenticação;
* CRUD de quartos;
* criação de reservas;
* validações;
* disponibilidade;
* reservas consecutivas;
* quantidade de quartos;
* cupons;
* pagamentos;
* concorrência;
* regras financeiras;
* importação.

Resultado atual:

```text
63 testes
226 assertions
0 falhas
```

---

## 📚 Swagger / OpenAPI

A API está documentada utilizando OpenAPI 3.

Documentação disponível em:

```text
/api/documentation
```

A documentação inclui autenticação, quartos, reservas e pagamentos, permitindo testar os endpoints diretamente pelo Swagger UI.

---

## 🧱 Arquitetura

O projeto utiliza uma separação baseada em responsabilidades:

```text
Controllers
    ↓
Form Requests
    ↓
Services
    ↓
Models
    ↓
Database
```

Principais responsabilidades:

* **Controllers:** entrada e resposta HTTP
* **Form Requests:** validação das requisições
* **Services:** regras de negócio
* **Resources:** padronização das respostas JSON
* **Models:** relacionamento e persistência
* **Commands:** processos executáveis via Artisan

Foram utilizados também:

* Dependency Injection
* Service Layer
* Transactions
* Pessimistic Locking
* Idempotência
* API Resources
* Exception Handling

---

## 🔒 Segurança e consistência

Entre as principais medidas adotadas:

* autenticação via Sanctum;
* validação através de Form Requests;
* autorização das rotas protegidas;
* transações para operações críticas;
* `lockForUpdate()` em operações concorrentes;
* cálculo financeiro exclusivamente no backend;
* prevenção de reservas conflitantes;
* identificadores externos para importação idempotente;
* tratamento de erros de importação;
* `LIBXML_NONET` durante leitura dos XMLs.

Valores monetários são tratados internamente em centavos durante os cálculos para reduzir problemas de precisão.

---

## 🐳 Docker

O ambiente Docker contém:

```text
Nginx
PHP 8.5 / PHP-FPM
MySQL 8
```

Os serviços são executados através do Docker Compose, permitindo reproduzir o ambiente sem depender da configuração local do PHP ou MySQL.

---

## 📌 Decisões importantes

### Identificadores dos XMLs

Os IDs presentes nos XMLs são tratados como `external_id`, enquanto o banco possui seus próprios IDs internos.

Isso mantém a integração externa separada da identificação interna da aplicação.

### Datas

Foi criado um cast específico para datas (`DateOnly`), garantindo que campos `DATE` sejam persistidos somente como `Y-m-d`.

Isso mantém o comportamento consistente entre MySQL e SQLite durante os testes.

### Taxas

A estrutura de reservas possui o campo `fees`, atualmente iniciado em `0`, deixando o modelo preparado para futuras taxas, juros ou serviços adicionais sem alterar a estrutura principal.

---

## 🎯 Diferenciais

Além dos requisitos básicos, foram implementados:

* Docker;
* Swagger / OpenAPI 3;
* PHPUnit;
* Sanctum;
* controle de disponibilidade;
* quantidade de unidades por quarto;
* cupons;
* pagamentos;
* controle de saldo;
* proteção contra concorrência;
* importação idempotente;
* registro de inconsistências;
* separação das regras de negócio em Services.

---

## 📂 Estrutura principal

```text
app/
├── Console/Commands
├── Http/
│   ├── Controllers
│   ├── Requests
│   └── Resources
├── Models
└── Services
    ├── Import
    └── Reservation

database/
├── migrations
└── seeders

routes/
├── api.php
└── console.php

tests/
├── Feature
└── Unit

docker/
└── nginx
```

## 👨‍💻 Autor

**Arthur Marques**

Projeto desenvolvido como desafio técnico para a Foco Tecnologia.
