<?php

declare(strict_types=1);

// Builds the guide Document: an API integration guide on US Letter, portrait then landscape then portrait.
// examples/core/guide.php saves it as PDF and examples/html/render.php as HTML.
use Papercut\Decorator\{Badge, Blockquote, Callout, Card, ChoiceList, CodeBlock, KeyValueList};
use Papercut\{Column, Document, ListNode, Page, PageNumber, QrCode, Row, Spacer, Table, TableRow, Text};
use Papercut\Style\Align;
use Papercut\Value\Edges;

use function Papercut\{in, percent, pt};

$endpoints = [
    ['GET', '/v2/shipments', 'List shipments, newest first. Filter by status or date.', 'Key', '600 / min'],
    ['POST', '/v2/shipments', 'Create a shipment and buy its label.', 'Key', '120 / min'],
    ['GET', '/v2/shipments/{id}', 'Fetch one shipment with its current status.', 'Key', '600 / min'],
    ['DELETE', '/v2/shipments/{id}', 'Cancel a shipment that has not been collected.', 'Key', '60 / min'],
    ['GET', '/v2/shipments/{id}/label', 'Download the label as a PDF or PNG.', 'Key', '300 / min'],
    ['POST', '/v2/pickups', 'Book a courier collection for a date and time window.', 'Key', '30 / min'],
    ['GET', '/v2/tracking/{code}', 'Read the tracking events for a parcel.', 'Public', '60 / min'],
    ['POST', '/v2/webhooks', 'Register an endpoint to receive shipment events.', 'Admin key', '10 / min'],
];
$methodTones = ['GET' => 'tip', 'POST' => 'note', 'DELETE' => 'danger'];

$errors = [
    ['400', 'invalid_request', 'A field is missing or malformed.', 'Read the field list in the response and resend.'],
    ['401', 'invalid_key', 'The key is wrong or revoked.', 'Check that a test key is not used in live mode.'],
    ['404', 'not_found', 'The shipment or pickup does not exist.', 'Check the id; cancelled shipments stay readable.'],
    ['429', 'rate_limited', 'Too many requests in the current minute.', 'Wait for the Retry-After header, then retry.'],
];

$steps = [
    'Create an account and open the Developers page.',
    'Copy your test key. It starts with pk_test_ and works only in the sandbox.',
    'Send the request below with your key in the Authorization header.',
    'Store the returned id and poll the tracking endpoint, or register a webhook.',
];

$headers = [
    ['Parcelpost-Event', 'shipment.delivered'],
    ['Parcelpost-Delivery', 'dlv_8f2c91'],
    ['Parcelpost-Timestamp', '1772452800'],
    ['Parcelpost-Signature', 'sha256=9b1c...e04f'],
    ['Content-Type', 'application/json'],
];

$checklist = [
    ['Switch from the test key to a live key', true],
    ['Store the key in server-side configuration, not in code', true],
    ['Verify the signature on every webhook', true],
    ['Return a 2xx within five seconds and do the work afterwards', false],
    ['Handle 429 responses by waiting for Retry-After', false],
    ['Send a test shipment and cancel it', false],
];

$curl = <<<'CODE'
curl https://api.example.com/v2/shipments \
  -H "Authorization: Bearer pk_test_51Hx0" \
  -H "Idempotency-Key: order-1042" \
  -d service=standard \
  -d 'parcel[weight_kg]=1.4' \
  -d 'to[postcode]=94107'
CODE;

$json = <<<'CODE'
{
  "id": "shp_7Q2m9x",
  "status": "label_created",
  "tracking_code": "PP1042883301",
  "price": { "amount": 8.45, "currency": "USD" },
  "label_url": "https://api.example.com/v2/shipments/shp_7Q2m9x/label"
}
CODE;

$verify = <<<'CODE'
<?php

$secret    = getenv('PARCELPOST_WEBHOOK_SECRET');
$timestamp = $_SERVER['HTTP_PARCELPOST_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_PARCELPOST_SIGNATURE'] ?? '';
$payload   = file_get_contents('php://input');

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

if (!hash_equals($expected, $signature) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(400);
    exit;
}
CODE;

// The same header on every page, with that page's section; it stretches to the page's width.
$header = fn (string $section) => Row::make(
    Text::make('Parcelpost API')->bold()->size(pt(11))->color('indigo-600'),
    Spacer::flex(),
    Text::make($section)->size(pt(9))->color('slate-500'),
)->padding(Edges::of(pt(0), pt(0), pt(6), pt(0)))
    ->borderWidth(Edges::of(pt(0), pt(0), pt(0.75), pt(0)))->borderColor('slate-200');

$footer = PageNumber::make('Page {page} of {total}')->center()->size(pt(8))->color('slate-500');

// Page 1: portrait.
$first = Column::make(
    Column::make(
        Text::make('INTEGRATION GUIDE')->bold()->size(pt(9))->color('indigo-600'),
        Text::h1('Create and track shipments'),
        Text::make('Version 2.1 | Updated March 2026')->size(pt(9))->color('slate-500'),
    )->gap(pt(3)),
    Text::make('The Parcelpost API lets your store buy shipping labels, book collections and follow parcels without leaving your own system. Every call is plain HTTPS with JSON bodies, and every call can be tried in the sandbox first.'),
    Callout::make('Test keys create sandbox shipments that never reach a carrier. Switch to a live key only after the checklist on the last page.')
        ->tone('warning')->title('Start with a test key'),
    Column::make(
        Text::h3('Set up')->keepWithNext(),
        ListNode::ordered(...$steps),
    )->gap(pt(4)),
    Column::make(
        Text::h3('Create a shipment')->keepWithNext(),
        CodeBlock::make($curl)->language('bash')->label('Request')->variant('caption'),
        CodeBlock::make($json)->language('json')->label('Response: 201 Created')->variant('strip'),
    )->gap(pt(8)),
)->gap(pt(12));

// Page 2: landscape.
$table = Table::make()->columns('0.9in 2.8in 1fr 1in 1in')
    ->headerRow(TableRow::make(
        Text::make('Method')->bold(),
        Text::make('Path')->bold(),
        Text::make('Description')->bold(),
        Text::make('Auth')->bold(),
        Text::make('Rate limit')->bold()->right(),
    )->background('slate-100'));
foreach ($endpoints as [$method, $path, $description, $auth, $limit]) {
    $table = $table->bodyRow(TableRow::make(
        Badge::make($method)->tone($methodTones[$method] ?? 'warning'),
        Text::markup('<code>' . htmlspecialchars($path) . '</code>'),
        $description,
        Text::make($auth)->color('slate-600'),
        Text::make($limit)->color('slate-600')->right(),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}

$errorTable = Table::make()->columns('0.7in 1.6in 3.2in 1fr')
    ->headerRow(TableRow::make(
        Text::make('Status')->bold(),
        Text::make('Code')->bold(),
        Text::make('Meaning')->bold(),
        Text::make('What to do')->bold(),
    )->background('slate-100'));
foreach ($errors as [$status, $code, $meaning, $action]) {
    $errorTable = $errorTable->bodyRow(TableRow::make(
        Text::make($status)->bold(),
        Text::markup('<code>' . htmlspecialchars($code) . '</code>'),
        $meaning,
        Text::make($action)->color('slate-600'),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}

$second = Column::make(
    Column::make(
        Text::h3('Endpoint reference')->keepWithNext(),
        Text::make('All paths are relative to https://api.example.com. Send the key as a bearer token; limits are per key.')->color('slate-600'),
    )->gap(pt(3)),
    $table,
    Column::make(Text::h3('Errors')->keepWithNext(), $errorTable)->gap(pt(4)),
)->gap(pt(12));

// Page 3: portrait.
$headerList = KeyValueList::make()->labelWidth(percent(45));
foreach ($headers as [$name, $value]) {
    $headerList = $headerList->row($name, $value);
}

$list = ChoiceList::checkbox();
foreach ($checklist as [$label, $done]) {
    $list = $list->option($label, selected: $done);
}

$third = Column::make(
    Column::make(
        Text::h3('Webhooks')->keepWithNext(),
        Text::make('Parcelpost calls your endpoint when a shipment changes state. Each delivery is signed, so check the signature before you trust the body.'),
    )->gap(pt(4)),
    CodeBlock::make($verify)->language('php')->label('verify.php')->variant('strip'),
    Row::make(
        Card::make($headerList)->title('Request headers')->grow(1),
        Column::make(
            QrCode::make('https://docs.example.com/parcelpost/webhooks')->width(in(1.1)),
            Text::make('Webhook docs')->size(pt(8))->color('slate-500'),
        )->gap(pt(4))->align(Align::Center),
    )->gap(pt(16)),
    Column::make(Text::h3('Go-live checklist')->keepWithNext(), $list)->gap(pt(6)),
    Blockquote::make('Retries are on our side. Answer quickly and keep your handler boring.')->cite('Parcelpost support team'),
)->gap(pt(12));

return Document::make(
    Page::letter()->margin(in(0.75))->header($header('Integration guide'))->body($first)->footer($footer),
    Page::letter()->landscape()->margin(in(0.75))->header($header('Integration guide | Reference'))->body($second)->footer($footer),
    Page::letter()->margin(in(0.75))->header($header('Integration guide | Webhooks'))->body($third)->footer($footer),
)->title('Parcelpost API integration guide')->author('Parcelpost')->createdAt(new DateTimeImmutable('2026-03-02T09:00:00Z'));
