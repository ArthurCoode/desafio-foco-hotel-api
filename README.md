# Foco Hotel API

## Sobre o projeto

O **Foco Hotel API** é uma API REST desenvolvida em Laravel para o gerenciamento de hotéis, quartos e reservas. O projeto inclui:

- importação de dados a partir de arquivos XML;
- regras de disponibilidade de quartos por período;
- cálculo financeiro das reservas (subtotal, desconto, taxas e total);
- aplicação de cupons de desconto.

O projeto foi desenvolvido como **desafio técnico**, com o objetivo de demonstrar organização de código, separação de responsabilidades, tratamento de inconsistências de dados e cobertura de regras de negócio por testes automatizados.

## Tecnologias

| Tecnologia | Uso no projeto |
|---|---|
| PHP 8.5 | Linguagem da aplicação |
| Laravel 13 | Framework da API |
| MySQL 8 | Banco de dados relacional |
| Composer | Gerenciamento de dependências PHP |
| PHPUnit | Testes automatizados |
| Laravel Sanctum | Presente como dependência do projeto, mas **não aplicado** às rotas atuais de quartos e reservas |
| Git | Controle de versão |

> **Autenticação:** as rotas de quartos e de reservas documentadas neste README não estão protegidas por autenticação no estado atual do projeto.

## Requisitos

- PHP 8.5, com as extensões exigidas pelo Laravel (incluindo `pdo_mysql`)
- Composer
- MySQL 8
- Git

## Instalação

1. Clone o repositório e acesse o diretório do projeto:

   ```bash
   git clone <url-do-repositorio>
   cd <diretorio-do-projeto>
   ```

2. Instale as dependências:

   ```bash
   composer install
   ```

3. Crie o arquivo de ambiente e gere a chave da aplicação:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure a conexão com o MySQL no arquivo `.env` (veja [Configuração do ambiente](#configuração-do-ambiente)).

5. Crie o banco de dados no MySQL, com o mesmo nome definido em `DB_DATABASE`. Exemplo:

   ```sql
   CREATE DATABASE nome_do_banco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

6. Execute as migrations:

   ```bash
   php artisan migrate
   ```

7. (Opcional) Importe os dados dos XMLs:

   ```bash
   php artisan hotel:import
   ```

8. (Opcional) Inicie o servidor de desenvolvimento local:

   ```bash
   php artisan serve
   ```

## Configuração do ambiente

As principais variáveis do `.env` relacionadas ao banco de dados são:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=nome_do_banco
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

| Variável | Descrição |
|---|---|
| `DB_CONNECTION` | Driver de conexão (`mysql`) |
| `DB_HOST` | Host do servidor MySQL |
| `DB_PORT` | Porta do MySQL (padrão `3306`) |
| `DB_DATABASE` | Nome do banco criado para o projeto |
| `DB_USERNAME` | Usuário com acesso ao banco |
| `DB_PASSWORD` | Senha do usuário |

A porta `3307` é a utilizada no ambiente deste projeto, mas a porta pode variar conforme a configuração local (a porta padrão do MySQL é `3306`). Ajuste `DB_PORT` de acordo com a sua instalação.

Os valores acima são apenas exemplos. O arquivo `.env` contém credenciais locais e não deve ser versionado.

## Banco de dados

| Tabela | Descrição |
|---|---|
| `hotels` | Hotéis cadastrados |
| `rooms` | Quartos de cada hotel, incluindo a quantidade (`quantity`) disponível |
| `reservations` | Reservas, com período, status e valores (`subtotal`, `discount`, `fees`, `total`) |
| `guests` | Hóspedes vinculados a uma reserva |
| `reservation_dailies` | Diárias de uma reserva (data e valor de cada noite) |
| `payments` | Pagamentos vinculados a reservas (estrutura existente; não há endpoint de pagamentos) |
| `coupons` | Cupons de desconto (`code`, `type`, `value`, `starts_at`, `expires_at`, `active`) |
| `reservation_coupons` | Cupom aplicado a uma reserva, com o valor de desconto efetivamente aplicado |
| `import_errors` | Registro de inconsistências encontradas durante a importação dos XMLs |

### Identificadores externos (`external_id`)

Os IDs presentes nos arquivos XML são tratados como **identificadores externos** e armazenados na coluna `external_id`. Eles **não substituem** os IDs internos (chaves primárias) do banco de dados: os relacionamentos entre tabelas usam sempre os IDs internos, e o `external_id` serve para localizar o registro correspondente quando a importação é executada novamente.

## Importação dos XMLs

O fluxo de importação é:

```text
XML
 → XmlImportService
 → serviços específicos de importação
 → Models/Eloquent
 → banco de dados
```

O comando Artisan apenas dispara o processo; a leitura dos XMLs e a persistência ficam nos serviços de `app/Services/Import`.

### Comando

```bash
php artisan hotel:import
```

O comando possui um **diretório padrão** de onde lê os arquivos. Para usar outro diretório, informe a opção `--path`:

```bash
php artisan hotel:import --path=/caminho/dos/xmls
```

Os arquivos XML esperados são:

- `hotels.xml`
- `rooms.xml`
- `reserves.xml`

### Idempotência

A importação utiliza os identificadores externos (`external_id`) com `updateOrCreate` (ou regra equivalente) para localizar registros já importados. Assim, executar o comando várias vezes não duplica hotéis, quartos ou reservas: registros existentes são atualizados e apenas os novos são criados.

### Tratamento de inconsistências

Inconsistências nos dados de origem **não são corrigidas silenciosamente**. Elas são registradas na tabela `import_errors`, e os dados importados são preservados.

A regra de período utilizada é: **check-in inclusivo e check-out exclusivo**. Uma diária só é considerada válida se sua data estiver em `[check_in, check_out)`.

Exemplo: a reserva 6 possui período de `2022-10-01` até `2022-10-04`, o que corresponde às noites de `2022-10-01`, `2022-10-02` e `2022-10-03`. Como ela contém uma diária em `2022-12-03`, fora desse período, a divergência é registrada em `import_errors`, sem alterar nem descartar os dados importados.

## Scheduler e CRON

O Laravel Scheduler está configurado em `routes/console.php` para executar o comando de importação **uma vez por hora**:

```php
Schedule::command('hotel:import')
    ->hourly()
    ->withoutOverlapping();
```

- `->hourly()`: executa o comando no minuto 0 de cada hora.
- `->withoutOverlapping()`: evita execuções simultâneas. Se uma importação ainda estiver em andamento quando a próxima for disparada, a nova execução é ignorada. O controle usa lock no cache, portanto o cache configurado deve ser compartilhado entre processos (por exemplo `database`, `redis` ou `file`).

### CRON, Scheduler e comando

| Componente | Papel |
|---|---|
| **CRON do sistema** | Serviço do sistema operacional que executa o Scheduler do Laravel a cada minuto |
| **Laravel Scheduler** | Avalia, a cada execução, quais tarefas estão no horário e as dispara |
| **`hotel:import`** | Comando que de fato realiza a importação dos XMLs |

Em produção (Linux), o CRON deve executar o Scheduler a cada minuto:

```bash
php artisan schedule:run
```

Exemplo de entrada de CRON (ajuste o caminho para o ambiente real):

```text
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para listar os agendamentos registrados:

```bash
php artisan schedule:list
```

Em desenvolvimento local, é possível executar o Scheduler em primeiro plano com `php artisan schedule:work`.

## API REST

Os endpoints da API utilizam respostas em JSON para as operações previstas, e as validações e regras de negócio são retornadas nesse formato. Os endpoints abaixo têm o prefixo `/api`. Para conferir as rotas registradas no ambiente, use:

```bash
php artisan route:list
```

### Quartos

A API de quartos é registrada com `apiResource`, que gera as rotas REST padrão:

| Método | Endpoint | Finalidade |
|---|---|---|
| `GET` | `/api/rooms` | Listar quartos |
| `POST` | `/api/rooms` | Criar quarto |
| `GET` | `/api/rooms/{room}` | Exibir um quarto |
| `PUT` / `PATCH` | `/api/rooms/{room}` | Atualizar um quarto |
| `DELETE` | `/api/rooms/{room}` | Remover um quarto |

Os parâmetros aceitos em cada operação são definidos pelos Form Requests e Controllers correspondentes.

### Reservas

| Método | Endpoint | Finalidade |
|---|---|---|
| `POST` | `/api/reservations` | Criar uma reserva |

Campos aceitos no corpo da requisição (validados por `StoreReservationRequest`):

| Campo | Obrigatório | Regras |
|---|---|---|
| `hotel_id` | Sim | Inteiro; deve existir em `hotels` |
| `room_id` | Sim | Inteiro; deve existir em `rooms` e pertencer ao hotel informado |
| `check_in` | Sim | Data no formato `Y-m-d` |
| `check_out` | Sim | Data no formato `Y-m-d`, posterior a `check_in` |
| `guests` | Sim | Array com ao menos um hóspede; cada item exige `name` (até 255 caracteres) e aceita `phone` (até 30 caracteres) |
| `dailies` | Sim | Array de diárias; cada item exige `date` (`Y-m-d`) e `amount` (numérico, não negativo). Deve haver exatamente uma diária por noite, sem datas duplicadas |
| `coupon_code` | Não | String de até 50 caracteres. Quando informado, o cupom é validado pelas regras de negócio |

Exemplo de requisição:

```json
{
  "hotel_id": 1,
  "room_id": 1,
  "check_in": "2026-11-10",
  "check_out": "2026-11-13",
  "guests": [
    { "name": "Maria Silva" }
  ],
  "dailies": [
    { "date": "2026-11-10", "amount": "200.00" },
    { "date": "2026-11-11", "amount": "200.00" },
    { "date": "2026-11-12", "amount": "200.00" }
  ],
  "coupon_code": "PROMO"
}
```

Os valores financeiros (`subtotal`, `discount`, `total`) são **calculados pelo backend** a partir das diárias e do cupom. Campos financeiros enviados pelo cliente são ignorados e não devem ser confiados.

Códigos HTTP esperados:

| Código | Situação |
|---|---|
| `201` | Reserva criada com sucesso |
| `422` | Dados inválidos ou cupom inexistente, inativo ou fora da validade; nenhuma reserva é criada |

## Regras de negócio

### Disponibilidade

Uma reserva existente **conflita** com o novo período quando:

```text
novo_check_in < reserva_existente_check_out
E
novo_check_out > reserva_existente_check_in
```

O **check-in é inclusivo** e o **check-out é exclusivo**: uma reserva que termina no mesmo dia em que outra começa não gera conflito.

Atualmente, somente reservas com status `confirmed` são consideradas ocupantes no cálculo de disponibilidade.

A disponibilidade também considera a quantidade (`quantity`) cadastrada para o quarto: o quarto permanece disponível enquanto o número de reservas conflitantes for menor que essa quantidade.

### Cálculo financeiro

- **Subtotal:** soma dos valores das diárias.
- **Desconto:** calculado pelo backend a partir do cupom informado; sem cupom, o desconto é `0.00`.
- **Taxas (`fees`):** atualmente iniciadas em zero.
- **Total:** `subtotal - desconto + taxas`, também calculado pelo backend.

Durante os cálculos, os valores monetários são convertidos para **centavos inteiros**, e as strings decimais são interpretadas diretamente, sem aritmética de ponto flutuante. Isso evita problemas de precisão. O arredondamento, quando há uma terceira casa decimal, é *half-up*.

### Cupons

- **Tipos:** `percentage` (percentual sobre o subtotal, de 0% a 100%) e `fixed` (valor fixo em reais).
- **Status:** cupons com `active = false` são rejeitados.
- **Validade:** `starts_at` e `expires_at` são opcionais. Quando informados, o cupom só é aceito dentro do intervalo, com os limites inclusivos.
- **Limite do desconto:** o desconto nunca ultrapassa o subtotal, portanto o total nunca fica negativo.
- **Registro:** o cupom aplicado é gravado na tabela `reservation_coupons`, com o valor do desconto, e o desconto também é persistido em `reservations.discount`.
- **Falhas:** cupom inexistente ou inválido interrompe a criação da reserva (a transação é revertida e nada é gravado).

O projeto **não** implementa limite de quantidade de usos por cupom nem outras restrições além das listadas acima.

## Testes

O projeto possui testes automatizados com PHPUnit, organizados nos seguintes grupos:

- **Regras de reserva:** criação de reservas e validações associadas;
- **Disponibilidade:** conflito de períodos e quantidade de quartos;
- **Cálculo financeiro:** subtotal, desconto, taxas e total;
- **Cupons:** validação, tipos e limites do desconto (`CouponService`);
- **API:** testes de Feature dos endpoints, incluindo a integração de cupons na criação de reservas.

Para executar a suíte:

```bash
php artisan test
```

## Estrutura do projeto

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
    ├── Reservation
    └── Room
```

| Camada | Responsabilidade |
|---|---|
| `Console/Commands` | Comandos Artisan, como `hotel:import`, que apenas disparam os serviços |
| `Http/Controllers` | Recebem as requisições e delegam às regras de negócio |
| `Http/Requests` | Validação do formato e da estrutura dos dados de entrada |
| `Http/Resources` | Padronização das respostas JSON |
| `Models` | Entidades Eloquent e seus relacionamentos |
| `Services/Import` | Leitura dos XMLs e persistência dos dados importados |
| `Services/Reservation` | Disponibilidade, cálculo financeiro, cupons e criação de reservas |
| `Services/Room` | Regras relacionadas a quartos |

## Decisões técnicas

- **Comando Artisan separado dos serviços de importação:** o comando só orquestra a execução; a lógica fica em serviços reutilizáveis e testáveis.
- **Services para regras de negócio:** Controllers permanecem enxutos, e as regras ficam em classes com responsabilidade única.
- **Form Requests para validação:** o Request valida apenas estrutura e formato (por exemplo, `coupon_code` como string de até 50 caracteres); a validade do cupom é decidida pelo `CouponService`.
- **API Resources:** padronizam o formato das respostas JSON.
- **Transactions na criação de reservas:** reserva, hóspedes, diárias e cupom aplicado são gravados atomicamente; qualquer falha reverte tudo.
- **Lock da sala:** a sala é bloqueada (`lockForUpdate`) durante a criação da reserva, o que serializa requisições concorrentes do mesmo quarto e evita overbooking.
- **Cálculo financeiro no backend:** nenhum valor financeiro enviado pelo cliente é utilizado.
- **IDs externos separados dos internos:** permitem importação idempotente sem acoplar o modelo interno ao XML.
- **Registro de inconsistências de importação:** problemas nos dados de origem são registrados em `import_errors` em vez de serem corrigidos silenciosamente.

## Melhorias futuras

As melhorias abaixo são possibilidades futuras e **não estão implementadas** no projeto atual:

- documentação da API com Swagger/OpenAPI;
- autenticação e autorização mais completas nas rotas da API;
- gestão de usuários e permissões;
- logs de aplicação mais estruturados;
- fluxo de pagamentos;
- taxas adicionais;
- limite de usos por cupom;
- interface frontend integrada à API.
