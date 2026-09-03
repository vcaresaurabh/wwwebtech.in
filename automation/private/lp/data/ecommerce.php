<?php
/* /lp/ecommerce/ — T2-4. The buyer sells already, on Shopify, WooCommerce
   or a marketplace, and is losing margin to fees or losing orders to a
   checkout that does not work on a phone. */
declare(strict_types=1);

return [
  'slug' => 'ecommerce', 'variant' => 'a',
  'title' => 'Ecommerce website development in India | Wwwebtech',
  'desc'  => 'Online stores built to load fast, take payments cleanly and feed your marketplaces from one place. You own the store and the customer data.',

  'h1'  => 'Your store loses the sale between the product page and the payment.',
  'sub' => 'Most Indian ecommerce sites are fast enough to browse and too slow to buy from. The checkout is where the money leaks, and it is measurable.',
  'chips' => ['One catalogue, every channel', 'Checkout built for Indian payments', 'You own the customer data'],
  'cta' => 'Get a scoped proposal',
  'cta_sub' => 'Two questions to start. No obligation, no sales sequence.',

  'match' => [
    'ecommerce-website-development' => 'Storefront, payments and stock in one build, by a Delhi team.',
    'shopify-development'           => 'Shopify done properly, or something you own outright — we will say which suits you.',
    'woocommerce-development'       => 'WooCommerce that stays fast as the catalogue grows.',
    'online-store'                  => 'A store built to be bought from on a phone, on patchy 4G.',
    'd2c-website'                   => 'Direct to consumer, without handing your margin to a marketplace.',
    'marketplace-integration'       => 'One catalogue feeding Amazon, Flipkart and your own store.',
  ],

  'trust' => [
    'GSTIN-registered business in Delhi',
    'You own the store, the code and the customer list',
    'Fixed written scope before any work starts',
    'Payment gateway set up in your name, not ours',
  ],

  'pains' => [
    ['“People add to cart and then vanish.”',
     'Almost always the checkout — too many steps, a slow page, or a payment method they do not trust. Each is fixable and each is measurable.'],
    ['“The marketplace takes most of the margin.”',
     'It also owns the customer. Your own store is the only channel where the second sale costs you nothing.'],
    ['“Updating stock in three places is a full-time job.”',
     'It should be one catalogue feeding every channel. Doing it by hand is not a process, it is a person.'],
  ],

  'gets' => [
    'A storefront that stays fast with a full catalogue',
    'A checkout built for how Indians actually pay — UPI, cards, netbanking, COD',
    'One product catalogue that feeds your own store and your marketplaces',
    'Stock that updates everywhere when it changes anywhere',
    'GST-compliant invoicing built in, not bolted on',
    'Shipping and tracking wired to your courier',
    'Abandoned-cart recovery that is useful rather than nagging',
    'Your customer list, exportable, in your control',
    'Reporting that shows margin, not just revenue',
  ],

  'proof_mode' => 'fallback',
  'proof_head' => 'We are a young firm. Here is what we can show you instead.',
  'proof_sub'  => 'No invented revenue figures and no logos we do not have permission to use.',

  'steps' => [
    ['Work out where the money leaks', 'Before building anything: which step loses the sale. Often the fix is smaller and cheaper than a rebuild.', 'Week 1'],
    ['Catalogue and checkout first',   'The two things that make money. Design follows what they need, not the other way round.', 'Weeks 1–3'],
    ['Payments, shipping, GST',        'Gateway in your name, courier connected, invoices compliant. Tested with real transactions.', 'Weeks 3–5'],
    ['Launch and hand over',           'Your team trained on orders and stock. Every account in your name.', 'Weeks 6–8'],
  ],

  'objections' => [
    ['Should we just use Shopify?',
     'Often yes, and we will say so. Shopify wins on speed to launch and on not having to think about infrastructure. It costs a monthly fee plus a cut, and there are things it will not let you change. Above a certain volume, owning the store is cheaper and more flexible.'],
    ['We already sell on Amazon. Why bother with our own store?',
     'Margin and ownership. On a marketplace you rent the customer; the second sale costs you the same commission as the first. Your own store is where repeat business is actually profitable.'],
    ['How do we handle COD?',
     'Built in, with the fraud and return handling that makes it survivable. Cash on delivery is still a large share of Indian ecommerce and pretending otherwise loses orders.'],
    ['What about GST invoicing?',
     'Generated automatically on order, in the format your accountant expects. Checked with your CA before launch rather than after.'],
    ['Can it connect to Tally?',
     'Usually. We confirm it during scoping against your specific setup rather than promising it in advance.'],
    ['Who owns the customer data?',
     'You do, exportable at any time. That is the entire argument for having your own store.'],
  ],

  'price_from' => null, 'price_note' => null,
  'price_moves' => [
    ['Catalogue size and complexity', 'Twenty products with two variants is not two thousand with size and colour.'],
    ['What it integrates with',       'A payment gateway is routine. Tally, a courier API and two marketplaces is real work.'],
    ['Whether stock is synced',       'One-way is simple. Two-way sync across channels is the hardest part of most builds.'],
    ['Migration from an existing store', 'Products, customers and order history each move differently.'],
  ],

  'faq' => [
    ['How much does an ecommerce website cost in India?',
     'It depends mostly on catalogue complexity and what the store has to integrate with. A clean catalogue with one payment gateway is a very different project from two thousand variants syncing to Amazon and Tally. We quote a fixed price after one scoping call.'],
    ['Shopify or a custom store?',
     'Shopify if you want to launch quickly and are happy with a monthly fee and a revenue share. Custom if you are past the volume where that arithmetic works, or you need something Shopify will not let you change. We will tell you which we think you are.'],
    ['Can customers pay by UPI?',
     'Yes, along with cards, netbanking, wallets and cash on delivery. UPI is how a large share of Indian customers expect to pay and leaving it out costs orders.'],
    ['Will it work with our courier?',
     'Most Indian couriers have an integration path. We check yours specifically during scoping.'],
    ['How long does it take?',
     'Six to eight weeks for a straightforward store. Catalogue preparation is usually the longest pole, and it is work only you can do.'],
    ['Can we migrate from our current store?',
     'Yes. Products and customers migrate cleanly; order history depends on the platform. We tell you what will and will not come across before you commit.'],
  ],

  'final_h2'  => 'Tell us where the sale is being lost.',
  'final_sub' => 'Two questions to start. If the fix is smaller than a rebuild, we will say so.',
];
