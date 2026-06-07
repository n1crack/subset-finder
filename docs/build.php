<?php

/**
 * Generates docs/index.html — the public examples page.
 *
 * Every code block on the page is executed by THIS script against the real
 * package, and its captured output is embedded next to it. Nothing on the
 * page is hand-written sample output.
 *
 * Regenerate with:  php docs/build.php
 */

require __DIR__ . '/../vendor/autoload.php';

/* ------------------------------------------------------------------ */
/* The snippets                                                        */
/* ------------------------------------------------------------------ */

$setup = [
    'id' => 'setup',
    'title' => 'Setup — your item class',
    'desc' => 'Collection items implement the small <code>Subsetable</code> interface. '
        . 'Property names are up to you; the solver only talks to the interface.',
    'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subsetable;

class Item implements Subsetable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public int $quantity,
        public readonly float $price,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
PHP,
];

$cases = [
    [
        'id' => 'promo',
        'title' => '🛒 Cart promotion',
        'question' => 'How many times does “any 5 drinks + any 2 snacks” apply to this cart — and which items does it capture?',
        'desc' => 'The classic cart-discount problem. The promo set is flexible (“any of these”), '
            . 'so the solver picks which units fall under the promo — cheapest first, thanks to '
            . '<code>sortField: \'price\'</code> — and tells you exactly what stays at full price.',
        'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

$cart = [
    new Item(1, 'Latte',     quantity: 7, price: 4.50),
    new Item(2, 'Espresso',  quantity: 4, price: 3.00),
    new Item(3, 'Croissant', quantity: 5, price: 3.75),
    new Item(4, 'Brownie',   quantity: 3, price: 4.25),
];

$promo = new SubsetCollection([
    Subset::of([1, 2])->take(5), // any 5 drinks
    Subset::of([3, 4])->take(2), // any 2 snacks
]);

$finder = new SubsetFinder($cart, $promo, new SubsetFinderConfig(sortField: 'price'));
$finder->solve();

printf("Promo applies %d times.\n\n", $finder->getSubsetQuantity());

echo "Discounted (cheapest first):\n";
foreach ($finder->getFoundSubsets() as $item) {
    printf("  %-9s x%d\n", $item->name, $item->quantity);
}

echo "\nFull price:\n";
foreach ($finder->getRemaining() as $item) {
    printf("  %-9s x%d\n", $item->name, $item->quantity);
}
PHP,
    ],
    [
        'id' => 'giftbox',
        'title' => '🎁 Gift box assembly',
        'question' => 'How many complete gift boxes can we assemble from current stock — and which ingredient runs out first?',
        'desc' => 'A box needs 2 candles, 3 soaps and a card — any variant of each. The answer '
            . 'tells you how many boxes to promise; the leftovers tell you what to restock '
            . '(here the cards are the bottleneck, not the soaps).',
        'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;

$stock = [
    new Item(1, 'Vanilla Candle', quantity: 40, price: 12.00),
    new Item(2, 'Pine Candle',    quantity: 25, price: 14.00),
    new Item(3, 'Lavender Soap',  quantity: 80, price: 6.50),
    new Item(4, 'Honey Soap',     quantity: 60, price: 7.00),
    new Item(5, 'Greeting Card',  quantity: 30, price: 2.50),
];

$giftBox = new SubsetCollection([
    Subset::of([1, 2])->take(2), // 2 candles, either scent
    Subset::of([3, 4])->take(3), // 3 soaps, either kind
    Subset::of([5])->take(1),    // 1 card
]);

$finder = new SubsetFinder($stock, $giftBox);
$finder->solve();

printf("Complete gift boxes: %d\n\n", $finder->getSubsetQuantity());

echo "Still on the shelf:\n";
foreach ($finder->getRemaining() as $item) {
    printf("  %-15s %3d left\n", $item->name, $item->quantity);
}

echo "\n=> Greeting cards are the bottleneck - restock those first.\n";
PHP,
    ],
    [
        'id' => 'assembly',
        'title' => '🏭 Assembly with substitutable parts',
        'question' => 'How many desks can we build when several parts are interchangeable?',
        'desc' => 'A desk needs 4 legs, a top and 8 screws — but oak or steel legs both work, '
            . 'and either screw type fits. Classic BOM math breaks down with substitutable '
            . 'parts; here each requirement is simply a subset over the alternatives.',
        'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

$parts = [
    new Item(1, 'Oak Leg',   quantity: 90,  price: 18.00),
    new Item(2, 'Steel Leg', quantity: 60,  price: 12.00),
    new Item(3, 'Oak Top',   quantity: 35,  price: 80.00),
    new Item(4, 'Glass Top', quantity: 10,  price: 95.00),
    new Item(5, 'Screw A',   quantity: 500, price: 0.10),
    new Item(6, 'Screw B',   quantity: 140, price: 0.08),
];

$desk = new SubsetCollection([
    Subset::of([1, 2])->take(4), // any 4 legs
    Subset::of([3, 4])->take(1), // any top
    Subset::of([5, 6])->take(8), // any 8 screws
]);

// Cheapest parts are consumed first
$finder = new SubsetFinder($parts, $desk, new SubsetFinderConfig(sortField: 'price'));
$finder->solve();

printf("Desks we can build: %d\n\n", $finder->getSubsetQuantity());

echo "Parts consumed:\n";
foreach ($finder->getFoundSubsets() as $part) {
    printf("  %-9s x%d\n", $part->name, $part->quantity);
}

echo "\nParts left in the bin:\n";
foreach ($finder->getRemaining() as $part) {
    printf("  %-9s x%d\n", $part->name, $part->quantity);
}
PHP,
    ],
    [
        'id' => 'capacity',
        'title' => '🎓 Selling coaching packages against available hours',
        'question' => 'How many coaching packages can we sell this month with the hours our tutors have left?',
        'desc' => 'Quantities don\'t have to be things on a shelf — here they are <em>hours</em>, '
            . 'which split freely across packages. One package includes 10 senior hours and 20 '
            . 'junior hours, from any mix of tutors; sorting by hourly rate books the cheapest '
            . 'qualified hours first.',
        'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

// quantity = hours available this month, price = hourly rate
$tutors = [
    new Item(1, 'Maya (senior)', quantity: 40, price: 80.0),
    new Item(2, 'Tom (senior)',  quantity: 25, price: 90.0),
    new Item(3, 'Alex (junior)', quantity: 60, price: 45.0),
    new Item(4, 'Sam (junior)',  quantity: 50, price: 40.0),
];

// One package = 10 senior hours + 20 junior hours, any mix of tutors
$package = new SubsetCollection([
    Subset::of([1, 2])->take(10),
    Subset::of([3, 4])->take(20),
]);

$finder = new SubsetFinder($tutors, $package, new SubsetFinderConfig(sortField: 'price'));
$finder->solve();

printf("Packages we can sell this month: %d\n\n", $finder->getSubsetQuantity());

echo "Hours booked (cheapest rate first):\n";
foreach ($finder->getFoundSubsets() as $tutor) {
    printf("  %-13s %3d hours\n", $tutor->name, $tutor->quantity);
}

echo "\nHours still free:\n";
foreach ($finder->getRemaining() as $tutor) {
    printf("  %-13s %3d hours\n", $tutor->name, $tutor->quantity);
}
PHP,
    ],
    [
        'id' => 'overlap',
        'title' => '⚖️ Overlapping demands on shared stock',
        'question' => 'Two recurring orders compete for the same product — how many rounds of both can we actually ship?',
        'desc' => 'Order Alpha accepts either widget; Order Beta insists on the Pro. Checking each '
            . 'order against the stock <em>independently</em> counts the shared Pro units twice and '
            . 'promises rounds you cannot ship. The solver allocates everything from one shared pool.',
        'code' => <<<'PHP'
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

$stock = [
    new Item(1, 'Widget Pro',  quantity: 100, price: 8.00),
    new Item(2, 'Widget Lite', quantity: 50,  price: 3.00),
];

$orders = [
    Subset::of([1, 2])->take(10), // Order Alpha: 10 of either widget per round
    Subset::of([1])->take(10),    // Order Beta: 10 Widget Pro per round
];

// The naive estimate: each order checked against the pool on its own.
$naive = PHP_INT_MAX;
foreach ($orders as $order) {
    $supply = 0;
    foreach ($stock as $item) {
        if (in_array($item->getId(), $order->items, true)) {
            $supply += $item->getQuantity();
        }
    }
    $naive = min($naive, intdiv($supply, $order->quantity));
}

$finder = new SubsetFinder($stock, new SubsetCollection($orders), new SubsetFinderConfig(sortField: 'price'));
$finder->solve();

printf("Naive estimate     : %2d rounds  (double counts the shared Pro stock)\n", $naive);
printf("Shared-pool answer : %2d rounds  (what you can actually ship)\n\n", $finder->getSubsetQuantity());

foreach ($finder->getFoundSubsets() as $item) {
    printf("  %-12s %3d used\n", $item->name, $item->quantity);
}
PHP,
    ],
];

/* ------------------------------------------------------------------ */
/* Execute every snippet against the real package                      */
/* ------------------------------------------------------------------ */

function runSnippet(string $code): string
{
    ob_start();

    try {
        eval($code);
    } catch (\Throwable $e) {
        ob_end_clean();
        fwrite(STDERR, "Snippet failed: {$e->getMessage()}\n");
        exit(1);
    }

    return ob_get_clean();
}

runSnippet($setup['code']); // declares Item, no output

foreach ($cases as &$case) {
    $case['output'] = runSnippet($case['code']);
}
unset($case);

/* ------------------------------------------------------------------ */
/* Render                                                              */
/* ------------------------------------------------------------------ */

ini_set('highlight.comment', '#8a919e');
ini_set('highlight.keyword', '#1d4ed8');
ini_set('highlight.string', '#047857');
ini_set('highlight.default', '#1c2433');
ini_set('highlight.html', '#9d4edd');

function codeBlock(string $code): string
{
    $html = highlight_string("<?php\n" . $code, true);
    // Drop the opening tag we added for the highlighter.
    $html = preg_replace('/&lt;\?php(<br\s*\/?>)?/', '', $html, 1);

    return '<div class="code"><div class="code-head"><span></span><span></span><span></span><b>PHP</b></div>' . $html . '</div>';
}

function outputBlock(string $output): string
{
    return '<div class="out"><div class="out-head">OUTPUT</div><pre>' . htmlspecialchars($output) . '</pre></div>';
}

$sections = '';

$sections .= '<section class="card" id="' . $setup['id'] . '">'
    . '<h2>' . $setup['title'] . '</h2>'
    . '<p class="sub">' . $setup['desc'] . '</p>'
    . codeBlock($setup['code'])
    . '</section>';

$toc = '';

foreach ($cases as $case) {
    $toc .= '<a href="#' . $case['id'] . '">' . $case['title'] . '</a>';

    $sections .= '<section class="card" id="' . $case['id'] . '">'
        . '<h2>' . $case['title'] . '</h2>'
        . '<p class="question">' . $case['question'] . '</p>'
        . '<p class="sub">' . $case['desc'] . '</p>'
        . codeBlock($case['code'])
        . outputBlock($case['output'])
        . '</section>';
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SubsetFinder — PHP package for set building from quantity pools</title>
<meta name="description" content="ozdemir/subset-finder: how many complete sets can you build from a pool of quantities? Cart promotions, gift boxes, assembly, staffing — with captured output.">
<style>
  :root {
    --bg: #f6f7fb;
    --card: #ffffff;
    --ink: #1c2433;
    --muted: #6b7280;
    --line: #e5e7eb;
    --accent: #2563eb;
    --term-bg: #16181d;
    --term-ink: #d6e2f0;
    --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--ink);
    font: 16px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  .wrap { max-width: 860px; margin: 0 auto; padding: 36px 20px 70px; }
  header { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
  h1 { font-size: 28px; margin: 0; letter-spacing: -0.02em; }
  h1 .dim { color: var(--muted); font-weight: 400; }
  .links a { color: var(--accent); text-decoration: none; margin-left: 16px; font-size: 14px; }
  .links a:hover { text-decoration: underline; }
  .tagline { color: var(--muted); margin: 10px 0 24px; max-width: 72ch; }
  nav.toc { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
  nav.toc a {
    border: 1px solid var(--line); background: var(--card); color: var(--ink);
    padding: 8px 14px; border-radius: 10px; font-size: 14px; text-decoration: none;
  }
  nav.toc a:hover { border-color: var(--accent); color: var(--accent); }
  .card {
    background: var(--card); border: 1px solid var(--line); border-radius: 14px;
    padding: 22px; margin-bottom: 26px;
  }
  .card h2 { margin: 0 0 6px; font-size: 19px; }
  .card .question { font-size: 15.5px; font-weight: 600; margin: 0 0 6px; }
  .card .sub { color: var(--muted); font-size: 14.5px; margin: 0 0 16px; }
  .card .sub code { font-family: var(--mono); font-size: 13px; background: var(--bg); border-radius: 5px; padding: 1px 5px; }

  .code {
    border: 1px solid var(--line); border-radius: 10px; overflow: hidden; margin-bottom: 14px;
    background: #fbfcfe;
  }
  .code-head {
    display: flex; align-items: center; gap: 6px; padding: 8px 12px;
    background: #f1f3f8; border-bottom: 1px solid var(--line);
  }
  .code-head span { width: 10px; height: 10px; border-radius: 50%; background: #d8dce5; }
  .code-head b { margin-left: auto; font-size: 11px; color: var(--muted); letter-spacing: 0.08em; }
  .code pre, .code code {
    display: block; margin: 0; padding: 14px 16px; overflow-x: auto;
    font-family: var(--mono); font-size: 13px; line-height: 1.6; white-space: pre;
  }

  .out { border-radius: 10px; overflow: hidden; border: 1px solid #0c0e12; }
  .out-head {
    background: #0c0e12; color: #8b96a5; font: 600 11px var(--mono);
    letter-spacing: 0.12em; padding: 7px 14px;
  }
  .out pre {
    margin: 0; padding: 14px 16px; background: var(--term-bg); color: var(--term-ink);
    font-family: var(--mono); font-size: 13px; line-height: 1.6; overflow-x: auto;
  }

  footer { color: var(--muted); font-size: 13.5px; text-align: center; margin-top: 8px; }
  footer code { font-family: var(--mono); background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 2px 8px; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>SubsetFinder <span class="dim">— use cases</span></h1>
    <nav class="links">
      <a href="https://github.com/n1crack/subset-finder">GitHub</a>
      <a href="https://packagist.org/packages/ozdemir/subset-finder">Packagist</a>
    </nav>
  </header>
  <p class="tagline">
    A dependency-free PHP package that answers: <em>“how many complete sets can I build from a
    pool of quantities, which items go into them, and what is left over?”</em>
    Cart promotions, gift boxes, assembly lines, shift rosters — same question, same three lines of code.
  </p>
  <nav class="toc">
    {$toc}
  </nav>
  {$sections}
  <footer>
    <p><code>composer require ozdemir/subset-finder</code></p>
    <p>Outputs on this page are captured from these snippets — regenerate with <code>php docs/build.php</code> · MIT licensed</p>
  </footer>
</div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/index.html', $html);

echo "docs/index.html generated (" . number_format(strlen($html)) . " bytes)\n";
