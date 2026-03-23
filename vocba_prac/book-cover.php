<?php
require_once __DIR__ . '/config.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? 'daily')));

$covers = [
  'daily' => [
    'title' => 'Daily life & campus',
    'subtitle' => 'Lectures, study plans, campus life',
    'bg1' => '#F8EEE1',
    'bg2' => '#DDEAF4',
    'accent' => '#3A4E6B',
    'shapes' => '<rect x="56" y="74" width="248" height="150" rx="22" fill="#FFF9F1"/><rect x="85" y="99" width="118" height="70" rx="14" fill="#D7E7F4"/><rect x="224" y="94" width="50" height="90" rx="16" fill="#7BA3C8"/><circle cx="249" cy="82" r="18" fill="#FFD6A5"/><rect x="86" y="190" width="180" height="12" rx="6" fill="#E6D1B2"/><rect x="84" y="228" width="40" height="24" rx="9" fill="#F0BE73"/><rect x="142" y="228" width="40" height="24" rx="9" fill="#F0BE73"/><rect x="200" y="228" width="40" height="24" rx="9" fill="#F0BE73"/>',
  ],
  'cs' => [
    'title' => 'CS core vocabulary',
    'subtitle' => 'Code, data, systems, debugging',
    'bg1' => '#E6EEF5',
    'bg2' => '#DDE9DD',
    'accent' => '#1F2E40',
    'shapes' => '<rect x="46" y="72" width="268" height="160" rx="24" fill="#1F2E40"/><rect x="72" y="98" width="120" height="18" rx="9" fill="#7BA3C8"/><rect x="72" y="134" width="90" height="12" rx="6" fill="#DCE9F3"/><rect x="72" y="162" width="140" height="12" rx="6" fill="#DCE9F3"/><rect x="72" y="190" width="84" height="12" rx="6" fill="#9FD4B3"/><path d="M244 122 L286 152 L244 182" stroke="#F4C96D" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="218" y="192" width="70" height="20" rx="10" fill="#30455F"/>',
  ],
  'mech' => [
    'title' => 'Mechanical engineering',
    'subtitle' => 'Machines, motion, manufacturing',
    'bg1' => '#EFE9DE',
    'bg2' => '#E0E7F0',
    'accent' => '#4A5E78',
    'shapes' => '<circle cx="132" cy="150" r="48" fill="#7BA3C8"/><circle cx="132" cy="150" r="20" fill="#F8F2E8"/><circle cx="208" cy="150" r="36" fill="#F4C96D"/><circle cx="208" cy="150" r="14" fill="#F8F2E8"/><rect x="92" y="212" width="160" height="18" rx="9" fill="#B9C9D9"/><rect x="74" y="232" width="196" height="18" rx="9" fill="#D1BA95"/><path d="M172 150 L188 150" stroke="#4A5E78" stroke-width="10" stroke-linecap="round"/><rect x="248" y="104" width="34" height="92" rx="12" fill="#F3A683"/>',
  ],
  'civil' => [
    'title' => 'Civil engineering',
    'subtitle' => 'Structures, materials, site work',
    'bg1' => '#F1ECE2',
    'bg2' => '#E1ECF4',
    'accent' => '#5E6D7A',
    'shapes' => '<rect x="60" y="208" width="240" height="26" rx="10" fill="#D9C4A3"/><rect x="84" y="148" width="44" height="60" rx="10" fill="#F4C96D"/><rect x="146" y="124" width="44" height="84" rx="10" fill="#7BA3C8"/><rect x="208" y="104" width="44" height="104" rx="10" fill="#9FD4B3"/><rect x="72" y="236" width="218" height="18" rx="9" fill="#C3D2E1"/><path d="M96 138 L96 92 L270 92 L270 138" stroke="#5E6D7A" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
  ],
  'traffic' => [
    'title' => 'Traffic & transport',
    'subtitle' => 'Road systems, transit, flow',
    'bg1' => '#E7EEF5',
    'bg2' => '#E8F1E7',
    'accent' => '#34485F',
    'shapes' => '<rect x="42" y="120" width="276" height="88" rx="24" fill="#3A4E6B"/><rect x="164" y="80" width="32" height="176" rx="12" fill="#516983"/><rect x="54" y="158" width="252" height="12" rx="6" fill="#F8F2E8"/><rect x="178" y="96" width="4" height="144" rx="2" fill="#F4C96D"/><rect x="178" y="116" width="4" height="18" rx="2" fill="#3A4E6B"/><rect x="178" y="150" width="4" height="18" rx="2" fill="#3A4E6B"/><rect x="178" y="184" width="4" height="18" rx="2" fill="#3A4E6B"/><circle cx="92" cy="216" r="18" fill="#F3A683"/><circle cx="268" cy="108" r="16" fill="#9FD4B3"/>',
  ],
  'math' => [
    'title' => 'Math foundations',
    'subtitle' => 'Proofs, calculus, algebra',
    'bg1' => '#F5EEE4',
    'bg2' => '#E4EDF4',
    'accent' => '#394A63',
    'shapes' => '<rect x="54" y="80" width="252" height="156" rx="24" fill="#FFF8F0"/><path d="M84 194 C122 136 154 136 192 194 S262 252 290 138" stroke="#7BA3C8" stroke-width="10" fill="none" stroke-linecap="round"/><path d="M96 118 L122 118 L104 160 L128 160" stroke="#F3A683" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="214" y="110" width="60" height="60" rx="14" fill="#F4C96D"/><rect x="230" y="126" width="28" height="28" rx="8" fill="#FFF8F0"/><rect x="82" y="214" width="210" height="10" rx="5" fill="#D6C19F"/>',
  ],
];

$cover = $covers[$slug] ?? $covers['daily'];

header('Content-Type: image/svg+xml; charset=UTF-8');

function esc(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 270" role="img" aria-labelledby="title desc">
  <title id="title"><?php echo esc($cover['title']); ?></title>
  <desc id="desc"><?php echo esc($cover['subtitle']); ?></desc>
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="<?php echo esc($cover['bg1']); ?>" />
      <stop offset="100%" stop-color="<?php echo esc($cover['bg2']); ?>" />
    </linearGradient>
  </defs>
  <rect width="360" height="270" fill="url(#bg)" />
  <circle cx="305" cy="48" r="40" fill="#FFFFFF" opacity=".28" />
  <circle cx="56" cy="232" r="52" fill="#FFFFFF" opacity=".16" />
  <?php echo $cover['shapes']; ?>
  <rect x="22" y="20" width="214" height="34" rx="17" fill="rgba(255,255,255,.72)" />
  <text x="39" y="42" fill="<?php echo esc($cover['accent']); ?>" font-size="17" font-family="Inter, Arial, sans-serif" font-weight="700"><?php echo esc($cover['title']); ?></text>
</svg>
