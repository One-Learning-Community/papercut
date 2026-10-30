<?php

declare(strict_types=1);

// Shared sample data for the Blade examples (used by render.php and by ExamplesTest).

// --- invoice ---------------------------------------------------------------
$items = [
    ['Layout engine consulting', 'Flexbox-style design review and API workshop', 12, 140.00],
    ['PDF backend integration', 'Font subsetting and deterministic output', 8, 140.00],
    ['Table pagination', 'Repeating headers, keep-together rules', 6, 140.00],
    ['Template migration', 'Port 14 legacy reports to components', 20, 120.00],
    ['Quality assurance', 'Visual regression suite and PDF validation', 10, 110.00],
    ['Documentation', 'Developer guide and examples', 5, 95.00],
];
$subtotal = array_sum(array_map(fn (array $i) => $i[2] * $i[3], $items));
$tax = round($subtotal * 0.0825, 2);

// --- transcript ------------------------------------------------------------
$subjects = ['Calculus', 'Linear Algebra', 'Data Structures', 'Algorithms', 'Physics', 'Statistics', 'Databases', 'Compilers', 'Networks', 'Ethics', 'Logic', 'Numerical Methods'];
$grades = ['A' => 4.0, 'A-' => 3.7, 'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7, 'C+' => 2.3];
$gradeKeys = array_keys($grades);
$courses = [];
for ($i = 0; $i < 60; $i++) {
    $courses[] = [
        sprintf('CS %d', 100 + $i * 7 % 400),
        $subjects[$i % count($subjects)] . ($i >= 12 ? ' ' . (intdiv($i, 12) + 1) : ''),
        sprintf('Term %d', intdiv($i, 8) + 1),
        3 + $i % 2,
        $gradeKeys[($i * 5 + intdiv($i, 3)) % count($gradeKeys)],
    ];
}
$credits = array_sum(array_column($courses, 3));
$points = array_sum(array_map(fn (array $c) => $c[3] * $grades[$c[4]], $courses));

// --- report ----------------------------------------------------------------
$prose = [
    'Papercut starts from a simple observation: most business documents are layouts, not drawings. An invoice is a column of blocks, a letterhead is a row with a flexible gap, and a transcript is a table that happens to be long. The library therefore models documents as trees of components and leaves the coordinate arithmetic to a layout engine.',
    'Every node is immutable. A modifier such as padding or bold returns a new instance, so a fragment can be reused in a dozen places without one caller changing what another sees. This also makes documents safe to build once and render many times, which matters in long-running workers.',
    'Layout follows a measure-then-place model. Each element reports the space it wants given the width on offer, and containers distribute the remainder using grow factors, gaps, and alignment. The vocabulary is deliberately close to flexbox, so anyone who has written modern CSS can predict the result.',
    'Text is shaped with real font metrics. Lines are broken at word boundaries, justified paragraphs stretch the gaps between words, and inline markup such as bold or italic spans is carried through the line breaker without losing its style. Widths come from the embedded font program, never from an estimate.',
    'Pagination is a first-class concern rather than an afterthought. Blocks can ask to be kept together, a heading can insist on staying with the paragraph that follows it, and tables repeat their header on every page they cross. When nothing else works, the engine splits a block at a line boundary and keeps going.',
    'Output is deterministic. Given the same input and a fixed creation date, two runs produce identical bytes. That property makes golden-file tests practical: a visual regression becomes a byte comparison, and a diff in the PDF points at a diff in the code.',
    'The backend is a thin seam. The layout engine produces a display list of rectangles, lines, text runs, and links in plain points with a top-left origin, and one adapter turns that list into PDF objects. Nothing above the adapter knows how a PDF is encoded, so the engine can be tested without parsing a single file.',
    'Failure is explicit. If a block cannot fit on a page even after every permitted split, the engine throws by default and names the offending node. Callers who prefer degraded output can opt into clipping the overflowing element, but silent corruption is never the default.',
    'Styling is data. Colors resolve from a named palette, sizes accept points, millimetres, or percentages, and a theme supplies defaults for family, size, weight, and line height. Because every modifier also accepts a string form, templates written in markup and code written in PHP describe the same documents.',
    'The result is a library that stays out of the way for simple documents and still holds together for hard ones: a forty-page ledger, a certificate with a decorative background, or a report whose tables must not be orphaned from their captions.',
];
$scenarios = [];
$kinds = ['single column text', 'nested rows', 'wide table', 'justified prose'];
for ($i = 1; $i <= 80; $i++) {
    $scenarios[] = [$i, sprintf('Sample scenario %d: %s', $i, $kinds[$i % 4]), 1 + $i % 9, 10 + ($i * 37) % 90];
}

// --- responses -------------------------------------------------------------
$responseItems = [
    ['type' => 'instruction', 'prompt' => 'Before you continue', 'text' => 'Answer from your own experience in the workshop. Skip anything you did not attend.'],
    ['type' => 'text', 'prompt' => 'What did you find most useful?', 'answer' => 'The pagination session. Seeing a table split across pages with a repeated header answered a question we had been stuck on for weeks.'],
    ['type' => 'text', 'prompt' => 'What should we change for next time?', 'answer' => ''],
    ['type' => 'choices', 'multiple' => true, 'prompt' => 'Which sessions did you attend?', 'options' => ['Layout basics', 'Pagination', 'Fonts and HTML export', 'Templates in Blade'], 'selected' => ['Layout basics', 'Pagination']],
    ['type' => 'choices', 'multiple' => false, 'prompt' => 'How would you rate the pace?', 'options' => ['Too slow', 'About right', 'Too fast'], 'selected' => ['About right']],
    ['type' => 'ratings', 'prompt' => 'How far do you agree?', 'rows' => [
        ['The examples matched problems I have at work.', 'Strongly agree', 'tip'],
        ['I could follow the material without preparation.', 'Agree', 'note'],
        ['The workshop was long enough to cover the topics in depth.', 'Disagree', 'warning'],
    ]],
    ['type' => 'quote', 'text' => 'I came for the PDFs and stayed for the pagination.', 'cite' => 'Jane Smith, closing round'],
];

// --- guide -----------------------------------------------------------------
$guideEndpoints = [
    ['GET', '/v2/shipments', 'List shipments, newest first. Filter by status or date.', 'Key', '600 / min'],
    ['POST', '/v2/shipments', 'Create a shipment and buy its label.', 'Key', '120 / min'],
    ['GET', '/v2/shipments/{id}', 'Fetch one shipment with its current status.', 'Key', '600 / min'],
    ['DELETE', '/v2/shipments/{id}', 'Cancel a shipment that has not been collected.', 'Key', '60 / min'],
    ['GET', '/v2/shipments/{id}/label', 'Download the label as a PDF or PNG.', 'Key', '300 / min'],
    ['POST', '/v2/pickups', 'Book a courier collection for a date and time window.', 'Key', '30 / min'],
    ['GET', '/v2/tracking/{code}', 'Read the tracking events for a parcel.', 'Public', '60 / min'],
    ['POST', '/v2/webhooks', 'Register an endpoint to receive shipment events.', 'Admin key', '10 / min'],
];

$guideMethodTones = ['GET' => 'tip', 'POST' => 'note', 'DELETE' => 'danger'];

$guideErrors = [
    ['400', 'invalid_request', 'A field is missing or malformed.', 'Read the field list in the response and resend.'],
    ['401', 'invalid_key', 'The key is wrong or revoked.', 'Check that a test key is not used in live mode.'],
    ['404', 'not_found', 'The shipment or pickup does not exist.', 'Check the id; cancelled shipments stay readable.'],
    ['429', 'rate_limited', 'Too many requests in the current minute.', 'Wait for the Retry-After header, then retry.'],
];

$guideSteps = [
    'Create an account and open the Developers page.',
    'Copy your test key. It starts with pk_test_ and works only in the sandbox.',
    'Send the request below with your key in the Authorization header.',
    'Store the returned id and poll the tracking endpoint, or register a webhook.',
];

$guideHeaders = [
    ['Parcelpost-Event', 'shipment.delivered'],
    ['Parcelpost-Delivery', 'dlv_8f2c91'],
    ['Parcelpost-Timestamp', '1772452800'],
    ['Parcelpost-Signature', 'sha256=9b1c...e04f'],
    ['Content-Type', 'application/json'],
];

$guideChecklist = [
    ['Switch from the test key to a live key', true],
    ['Store the key in server-side configuration, not in code', true],
    ['Verify the signature on every webhook', true],
    ['Return a 2xx within five seconds and do the work afterwards', false],
    ['Handle 429 responses by waiting for Retry-After', false],
    ['Send a test shipment and cancel it', false],
];

$guideVerify = <<<'CODE'
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

// --- article ---------------------------------------------------------------
// In an app this string comes from the database: what CKEditor 5 saved (its output shape: tables in a figure, quotes with a paragraph, code with a language class).
$articleHtml = <<<'HTML'
<h2>Before you start</h2>
<p>Two-factor sign-in asks for a six-digit code from your phone <em>after</em> your password. Even if someone learns your password, they can't open your account without that code. You need:</p>
<ul>
  <li>An <strong>authenticator app</strong> on your phone, such as the one that comes with your password manager</li>
  <li>About five minutes, and your phone within reach</li>
  <li>Owner or Admin access, if you also want to require it for your whole workspace</li>
</ul>

<h2>Turn on two-factor sign-in</h2>
<ol>
  <li>Open <strong>Account settings</strong> and choose <strong>Security</strong>.</li>
  <li>Under <em>Two-factor sign-in</em>, select <strong>Set up</strong> and pick <strong>Authenticator app</strong>.</li>
  <li>Add Plotline to your authenticator app:
    <ul>
      <li>Scan the QR code on the screen, or</li>
      <li>Choose "Enter a setup key" and type the key shown under the code.</li>
    </ul>
  </li>
  <li>Type the six-digit code your app shows into the <code>Verification code</code> box and select <strong>Confirm</strong>.</li>
  <li>Save your recovery codes somewhere safe. You'll see them only once.</li>
</ol>
<p><strong>Note:</strong> Codes change every 30 seconds. If Plotline says a code is wrong, check that your phone's clock is set automatically.</p>

<h3>Turn it on from the command line</h3>
<p>If you manage accounts with the <a href="https://help.example.com/plotline/cli">Plotline CLI</a>, one command starts the same setup:</p>
<pre><code class="language-bash">plotline auth 2fa enable --method app
plotline auth 2fa status</code></pre>
<p>The first command prints a setup key. The second shows <code>enabled</code> once you've confirmed a code.</p>

<h2>Recovery codes</h2>
<p>Each account gets ten single-use recovery codes. A recovery code works in place of the six-digit code, so keep them where you'd keep a spare key.</p>
<blockquote><p>Treat recovery codes like passwords. Anyone who has one can sign in as you.</p></blockquote>

<h2>Require it for your workspace</h2>
<p>Owners and Admins can make two-factor sign-in mandatory. Members who haven't set it up are asked to do so the next time they sign in, and they can't open any project until they have.</p>
<p>To turn it on, open <strong>Workspace settings</strong>, choose <strong>Security</strong> and switch on <strong>Require two-factor sign-in</strong>. You can set a grace period of 0, 7 or 30 days.</p>
<p>Guests are not affected, because they sign in with a one-time link rather than a password.</p>

<h2>If something goes wrong</h2>
<p>Find what you see in the first column. If nothing here matches, write to <a href="mailto:help@example.com">help@example.com</a> and tell us which step failed.</p>
<figure class="table">
  <table>
    <thead>
      <tr><th>What you see</th><th>Likely cause</th><th>What to do</th></tr>
    </thead>
    <tbody>
      <tr><td>"That code didn't work"</td><td>The phone's clock is off, or the code expired</td><td>Turn on automatic time, then enter the next code</td></tr>
      <tr><td>No QR code appears</td><td>The page was blocked from loading images</td><td>Use the setup key instead</td></tr>
      <tr><td>You lost your phone</td><td>The authenticator app is gone</td><td>Sign in with a recovery code, then set up a new device</td></tr>
      <tr><td>No codes left</td><td>All ten were used</td><td>Ask a workspace Owner to reset your two-factor sign-in</td></tr>
    </tbody>
  </table>
</figure>
HTML;

return [
    'invoice' => [
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $subtotal + $tax,
    ],
    'transcript' => [
        'student' => 'Ada Lovelace',
        'courses' => $courses,
        'credits' => $credits,
        'points' => $points,
        'gpa' => $points / $credits,
    ],
    'report' => [
        'prose' => $prose,
        'scenarios' => $scenarios,
        'glossary' => [
            ['Display list', 'The ordered drawing operations produced by layout.'],
            ['Fragment', 'The part of a node that fits on one page.'],
            ['Track', 'A column width definition in a table.'],
        ],
    ],
    'responses' => [
        'respondent' => 'Jane Smith',
        'submitted' => '2026-03-02 14:30',
        'course' => 'Papercut workshop, spring cohort',
        'items' => $responseItems,
    ],
    'guide' => [
        'endpoints' => $guideEndpoints,
        'methodTones' => $guideMethodTones,
        'errors' => $guideErrors,
        'steps' => $guideSteps,
        'headers' => $guideHeaders,
        'checklist' => $guideChecklist,
        'verify' => $guideVerify,
    ],
    'article' => [
        'html' => $articleHtml,
    ],
    'certificate' => [
        'name' => 'Priya Raman',
        'course' => 'Introduction to Beekeeping',
        'date' => '12 March 2026',
    ],
];
