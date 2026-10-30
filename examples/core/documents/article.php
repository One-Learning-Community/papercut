<?php

declare(strict_types=1);

// Builds the knowledge base article Document: HTML as a rich text editor saves it, converted by Markup and wrapped in a page
// with a header and a page-number footer. examples/core/article.php saves it as PDF, examples/html/render.php as HTML
// and examples/docx/render.php as Word.
use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Text};
use Papercut\Markup\Markup;
use Papercut\Value\Edges;

use function Papercut\{mm, pt};

// In an app this string comes from the database: what CKEditor 5 saved (its output shape: tables in a figure, quotes with a paragraph, code with a language class).
$html = <<<'HTML'
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

$header = Row::make(
    Text::make('Plotline Help Center')->bold()->size(pt(11))->color('indigo-600'),
    Spacer::flex(),
    Text::make('Account and security')->size(pt(9))->color('slate-500'),
)->padding(pt(0), pt(0), pt(6), pt(0))->borderWidth(Edges::of(pt(0), pt(0), pt(0.75), pt(0)))->borderColor('slate-200');

$footer = PageNumber::make('Page {page} of {total}')->center()->size(pt(8))->color('slate-500');

$article = Column::make(
    Column::make(
        Text::h1('Setting up two-factor sign-in'),
        Text::make('Updated 12 March 2026 · 4 min read')->size(pt(9))->color('slate-500'),
    )->gap(pt(4)),
    Markup::html($html),
)->gap(pt(12));

return Document::make(
    Page::a4()->margin(mm(20))->header($header)->body($article)->footer($footer),
)->title('Setting up two-factor sign-in')->author('Plotline')->createdAt(new DateTimeImmutable('2026-03-12T09:00:00Z'));
