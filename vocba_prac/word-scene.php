<?php
require_once __DIR__ . '/config.php';

$word = strtolower(trim((string)($_GET['word'] ?? 'vocabulary')));

$sceneMap = [
  'syllabus' => ['type' => 'document', 'label' => 'course plan'],
  'lecture' => ['type' => 'lecture', 'label' => 'classroom'],
  'seminar' => ['type' => 'seminar', 'label' => 'discussion table'],
  'assignment' => ['type' => 'assignment', 'label' => 'task sheet'],
  'deadline' => ['type' => 'deadline', 'label' => 'calendar due date'],
  'tutorial' => ['type' => 'tutorial', 'label' => 'teacher support'],
  'revision' => ['type' => 'revision', 'label' => 'study review'],
  'attendance' => ['type' => 'attendance', 'label' => 'checklist'],
  'cafeteria' => ['type' => 'cafeteria', 'label' => 'campus meal'],
  'library' => ['type' => 'library', 'label' => 'bookshelves'],
  'roommate' => ['type' => 'roommate', 'label' => 'shared room'],
  'timetable' => ['type' => 'timetable', 'label' => 'weekly schedule'],
  'presentation' => ['type' => 'presentation', 'label' => 'speaker and screen'],
  'notebook' => ['type' => 'notebook', 'label' => 'notes'],
  'laboratory' => ['type' => 'laboratory', 'label' => 'lab bench'],
  'algorithm' => ['type' => 'algorithm', 'label' => 'flowchart'],
  'variable' => ['type' => 'variable', 'label' => 'named value'],
  'function' => ['type' => 'function', 'label' => 'code block'],
  'compiler' => ['type' => 'compiler', 'label' => 'terminal build'],
  'database' => ['type' => 'database', 'label' => 'data storage'],
  'network' => ['type' => 'network', 'label' => 'connected nodes'],
  'interface' => ['type' => 'interface', 'label' => 'app screen'],
  'recursion' => ['type' => 'recursion', 'label' => 'tree structure'],
  'debugging' => ['type' => 'debugging', 'label' => 'bug fixing'],
  'syntax' => ['type' => 'syntax', 'label' => 'code grammar'],
  'framework' => ['type' => 'framework', 'label' => 'app structure'],
  'repository' => ['type' => 'repository', 'label' => 'version history'],
  'encryption' => ['type' => 'encryption', 'label' => 'secure lock'],
  'array' => ['type' => 'array', 'label' => 'ordered values'],
  'backend' => ['type' => 'backend', 'label' => 'server side'],
];

$scene = $sceneMap[$word] ?? ['type' => 'generic', 'label' => 'study scene'];

header('Content-Type: image/svg+xml; charset=UTF-8');

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function scene_shapes(string $type): string {
  switch ($type) {
    case 'document':
      return '<rect x="120" y="80" width="360" height="500" rx="28" fill="#FFF9F0"/><rect x="390" y="80" width="90" height="90" rx="0" fill="#F2E5CE"/><path d="M390 80 L480 170 L390 170 Z" fill="#E7D4B3"/><rect x="165" y="180" width="190" height="22" rx="11" fill="#7BA3C8"/><rect x="165" y="230" width="260" height="14" rx="7" fill="#C9D8E7"/><rect x="165" y="270" width="230" height="14" rx="7" fill="#C9D8E7"/><rect x="165" y="310" width="210" height="14" rx="7" fill="#C9D8E7"/><rect x="165" y="370" width="90" height="90" rx="18" fill="#F4C96D"/><rect x="280" y="370" width="90" height="90" rx="18" fill="#9FD4B3"/><rect x="395" y="370" width="90" height="90" rx="18" fill="#F3A683"/>';
    case 'lecture':
      return '<rect x="70" y="130" width="500" height="300" rx="28" fill="#FFF8EE"/><rect x="120" y="170" width="220" height="130" rx="18" fill="#DCE9F3"/><rect x="370" y="165" width="90" height="160" rx="24" fill="#7BA3C8"/><circle cx="415" cy="145" r="34" fill="#FFD6A5"/><rect x="390" y="200" width="50" height="115" rx="16" fill="#4A6A8A"/><rect x="120" y="340" width="360" height="18" rx="9" fill="#E6D1B2"/><rect x="120" y="390" width="70" height="42" rx="12" fill="#E7B56A"/><rect x="220" y="390" width="70" height="42" rx="12" fill="#E7B56A"/><rect x="320" y="390" width="70" height="42" rx="12" fill="#E7B56A"/><rect x="420" y="390" width="70" height="42" rx="12" fill="#E7B56A"/>';
    case 'seminar':
      return '<ellipse cx="300" cy="320" rx="180" ry="80" fill="#E7D4B3"/><circle cx="205" cy="220" r="42" fill="#FFD6A5"/><circle cx="395" cy="220" r="42" fill="#FFD6A5"/><circle cx="300" cy="165" r="42" fill="#FFD6A5"/><rect x="168" y="260" width="74" height="98" rx="24" fill="#7BA3C8"/><rect x="358" y="260" width="74" height="98" rx="24" fill="#9FD4B3"/><rect x="263" y="205" width="74" height="118" rx="24" fill="#F3A683"/><rect x="250" y="295" width="100" height="38" rx="14" fill="#FFF8EE"/>';
    case 'assignment':
      return '<rect x="105" y="90" width="390" height="470" rx="26" fill="#FFF8EE"/><rect x="140" y="115" width="320" height="46" rx="18" fill="#7BA3C8"/><text x="170" y="145" fill="#FFFFFF" font-size="24" font-family="Inter, Arial, sans-serif" font-weight="700">Assignment</text><rect x="155" y="185" width="32" height="32" rx="8" fill="#9FD4B3"/><path d="M163 201 L173 211 L189 191" stroke="#2E6E51" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="210" y="193" width="190" height="16" rx="8" fill="#B8CADC"/><rect x="155" y="265" width="32" height="32" rx="8" fill="#F4C96D"/><rect x="210" y="273" width="220" height="16" rx="8" fill="#B8CADC"/><rect x="155" y="345" width="32" height="32" rx="8" fill="#F3A683"/><rect x="210" y="353" width="170" height="16" rx="8" fill="#B8CADC"/><circle cx="404" cy="425" r="52" fill="none" stroke="#D9415B" stroke-width="12"/><path d="M404 395 L404 425 L428 439" stroke="#D9415B" stroke-width="12" fill="none" stroke-linecap="round"/>';
    case 'deadline':
      return '<rect x="120" y="110" width="360" height="390" rx="28" fill="#FFF8EE"/><rect x="120" y="110" width="360" height="90" rx="28" fill="#7BA3C8"/><rect x="170" y="245" width="80" height="70" rx="16" fill="#F4C96D"/><rect x="270" y="245" width="80" height="70" rx="16" fill="#F4C96D"/><rect x="370" y="245" width="80" height="70" rx="16" fill="#F4C96D"/><rect x="170" y="335" width="80" height="70" rx="16" fill="#F4C96D"/><rect x="270" y="335" width="80" height="70" rx="16" fill="#F3A683"/><rect x="370" y="335" width="80" height="70" rx="16" fill="#F4C96D"/><circle cx="310" cy="370" r="48" fill="none" stroke="#D9415B" stroke-width="12"/><path d="M310 343 L310 370 L334 386" stroke="#D9415B" stroke-width="12" fill="none" stroke-linecap="round"/>';
    case 'tutorial':
      return '<rect x="95" y="130" width="410" height="310" rx="28" fill="#FFF8EE"/><circle cx="210" cy="230" r="42" fill="#FFD6A5"/><rect x="170" y="270" width="80" height="120" rx="24" fill="#7BA3C8"/><circle cx="390" cy="215" r="38" fill="#FFD6A5"/><rect x="355" y="250" width="70" height="110" rx="22" fill="#9FD4B3"/><rect x="250" y="220" width="95" height="65" rx="18" fill="#FFF9F0"/><path d="M270 250 L292 272 L330 228" stroke="#4A6A8A" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    case 'revision':
      return '<rect x="110" y="120" width="380" height="320" rx="28" fill="#FFF8EE"/><rect x="140" y="150" width="150" height="220" rx="20" fill="#DCE9F3"/><rect x="310" y="150" width="150" height="220" rx="20" fill="#FFF9F0"/><rect x="162" y="185" width="106" height="16" rx="8" fill="#7BA3C8"/><rect x="162" y="220" width="92" height="12" rx="6" fill="#B8CADC"/><rect x="162" y="250" width="98" height="12" rx="6" fill="#B8CADC"/><rect x="162" y="280" width="86" height="12" rx="6" fill="#B8CADC"/><rect x="338" y="188" width="96" height="24" rx="12" fill="#F4C96D"/><rect x="338" y="228" width="96" height="24" rx="12" fill="#9FD4B3"/><rect x="338" y="268" width="96" height="24" rx="12" fill="#F3A683"/><circle cx="300" cy="410" r="46" fill="#9FD4B3"/><path d="M278 410 L294 426 L324 392" stroke="#2E6E51" stroke-width="14" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    case 'attendance':
      return '<rect x="130" y="100" width="340" height="420" rx="26" fill="#FFF8EE"/><rect x="170" y="160" width="32" height="32" rx="8" fill="#9FD4B3"/><path d="M178 176 L188 186 L204 166" stroke="#2E6E51" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="220" y="168" width="180" height="16" rx="8" fill="#C9D8E7"/><rect x="170" y="235" width="32" height="32" rx="8" fill="#9FD4B3"/><path d="M178 251 L188 261 L204 241" stroke="#2E6E51" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="220" y="243" width="180" height="16" rx="8" fill="#C9D8E7"/><rect x="170" y="310" width="32" height="32" rx="8" fill="#F3A683"/><rect x="220" y="318" width="180" height="16" rx="8" fill="#C9D8E7"/><rect x="170" y="385" width="32" height="32" rx="8" fill="#F4C96D"/><rect x="220" y="393" width="180" height="16" rx="8" fill="#C9D8E7"/>';
    case 'cafeteria':
      return '<rect x="85" y="150" width="430" height="260" rx="32" fill="#FFF8EE"/><rect x="120" y="185" width="120" height="150" rx="22" fill="#F4C96D"/><circle cx="180" cy="238" r="30" fill="#FFF9F0"/><rect x="285" y="200" width="165" height="26" rx="13" fill="#7BA3C8"/><rect x="285" y="245" width="145" height="22" rx="11" fill="#9FD4B3"/><rect x="285" y="285" width="155" height="22" rx="11" fill="#F3A683"/><ellipse cx="300" cy="415" rx="150" ry="22" fill="#E7D4B3"/>';
    case 'library':
      return '<rect x="90" y="120" width="420" height="360" rx="28" fill="#FFF8EE"/><rect x="130" y="170" width="90" height="230" rx="18" fill="#7BA3C8"/><rect x="235" y="170" width="90" height="230" rx="18" fill="#9FD4B3"/><rect x="340" y="170" width="90" height="230" rx="18" fill="#F3A683"/><rect x="150" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/><rect x="180" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/><rect x="255" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/><rect x="285" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/><rect x="360" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/><rect x="390" y="195" width="16" height="180" rx="8" fill="#FFF9F0"/>';
    case 'roommate':
      return '<rect x="90" y="140" width="420" height="280" rx="28" fill="#FFF8EE"/><rect x="125" y="235" width="145" height="110" rx="18" fill="#9FD4B3"/><rect x="330" y="235" width="145" height="110" rx="18" fill="#7BA3C8"/><rect x="125" y="205" width="145" height="30" rx="14" fill="#FFF9F0"/><rect x="330" y="205" width="145" height="30" rx="14" fill="#FFF9F0"/><circle cx="185" cy="180" r="26" fill="#FFD6A5"/><circle cx="390" cy="180" r="26" fill="#FFD6A5"/><rect x="278" y="160" width="36" height="165" rx="16" fill="#E7D4B3"/>';
    case 'timetable':
      return '<rect x="105" y="100" width="390" height="430" rx="26" fill="#FFF8EE"/><rect x="145" y="140" width="310" height="58" rx="16" fill="#7BA3C8"/><text x="188" y="177" fill="#FFFFFF" font-size="24" font-family="Inter, Arial, sans-serif" font-weight="700">Timetable</text><rect x="145" y="220" width="70" height="230" rx="16" fill="#E7D4B3"/><rect x="235" y="220" width="70" height="230" rx="16" fill="#F4C96D"/><rect x="325" y="220" width="70" height="230" rx="16" fill="#9FD4B3"/><rect x="415" y="220" width="40" height="230" rx="16" fill="#F3A683"/><rect x="160" y="246" width="40" height="20" rx="10" fill="#FFF9F0"/><rect x="250" y="286" width="40" height="20" rx="10" fill="#FFF9F0"/><rect x="340" y="326" width="40" height="20" rx="10" fill="#FFF9F0"/><rect x="160" y="366" width="40" height="20" rx="10" fill="#FFF9F0"/><rect x="250" y="406" width="40" height="20" rx="10" fill="#FFF9F0"/>';
    case 'presentation':
      return '<rect x="90" y="120" width="420" height="320" rx="28" fill="#FFF8EE"/><rect x="130" y="150" width="240" height="165" rx="18" fill="#DCE9F3"/><rect x="185" y="330" width="130" height="16" rx="8" fill="#E7D4B3"/><rect x="238" y="345" width="24" height="52" rx="12" fill="#E7D4B3"/><circle cx="425" cy="210" r="34" fill="#FFD6A5"/><rect x="393" y="245" width="64" height="112" rx="22" fill="#7BA3C8"/><path d="M205 225 L245 190 L292 225 L335 180" stroke="#3A4E6B" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    case 'notebook':
      return '<rect x="120" y="90" width="360" height="470" rx="24" fill="#F7F0E2"/><rect x="160" y="90" width="36" height="470" fill="#E7D4B3"/><circle cx="178" cy="150" r="6" fill="#FFF9F0"/><circle cx="178" cy="210" r="6" fill="#FFF9F0"/><circle cx="178" cy="270" r="6" fill="#FFF9F0"/><circle cx="178" cy="330" r="6" fill="#FFF9F0"/><circle cx="178" cy="390" r="6" fill="#FFF9F0"/><rect x="220" y="160" width="200" height="14" rx="7" fill="#C9D8E7"/><rect x="220" y="220" width="180" height="14" rx="7" fill="#C9D8E7"/><rect x="220" y="280" width="210" height="14" rx="7" fill="#C9D8E7"/><path d="M250 420 L375 300" stroke="#F3A683" stroke-width="18" stroke-linecap="round"/>';
    case 'laboratory':
      return '<rect x="80" y="140" width="440" height="300" rx="30" fill="#FFF8EE"/><rect x="120" y="330" width="360" height="24" rx="12" fill="#E7D4B3"/><path d="M190 215 L220 330 L130 330 Z" fill="#7BA3C8"/><rect x="165" y="165" width="50" height="55" rx="14" fill="#DCE9F3"/><path d="M410 200 C410 165 360 165 360 200 L360 240 C360 280 410 280 410 240 Z" fill="#9FD4B3"/><rect x="355" y="185" width="60" height="16" rx="8" fill="#FFF9F0"/><circle cx="280" cy="235" r="44" fill="#F4C96D"/><circle cx="280" cy="235" r="22" fill="#FFF9F0"/>';
    case 'algorithm':
      return '<rect x="200" y="110" width="200" height="66" rx="20" fill="#7BA3C8"/><text x="242" y="151" fill="#FFFFFF" font-size="26" font-family="Inter, Arial, sans-serif" font-weight="700">Start</text><path d="M300 176 L300 220" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M220 220 L380 220 L420 270 L300 350 L180 270 Z" fill="#F4C96D"/><text x="247" y="279" fill="#3A4E6B" font-size="22" font-family="Inter, Arial, sans-serif" font-weight="700">if ?</text><path d="M220 270 L135 270" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M380 270 L465 270" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><rect x="60" y="236" width="110" height="68" rx="20" fill="#9FD4B3"/><rect x="430" y="236" width="110" height="68" rx="20" fill="#F3A683"/><text x="95" y="278" fill="#2E6E51" font-size="22" font-family="Inter, Arial, sans-serif" font-weight="700">Yes</text><text x="470" y="278" fill="#8C4B2D" font-size="22" font-family="Inter, Arial, sans-serif" font-weight="700">No</text><path d="M115 304 L115 356 L300 356" stroke="#3A4E6B" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M485 304 L485 356 L300 356" stroke="#3A4E6B" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="215" y="356" width="170" height="72" rx="22" fill="#FFF8EE"/><text x="242" y="401" fill="#3A4E6B" font-size="24" font-family="Inter, Arial, sans-serif" font-weight="700">Output</text>';
    case 'variable':
      return '<rect x="110" y="170" width="380" height="250" rx="28" fill="#FFF8EE"/><rect x="150" y="210" width="140" height="90" rx="20" fill="#7BA3C8"/><rect x="310" y="210" width="140" height="90" rx="20" fill="#F4C96D"/><rect x="150" y="320" width="300" height="55" rx="18" fill="#DCE9F3"/><circle cx="205" cy="255" r="18" fill="#FFF9F0"/><circle cx="380" cy="255" r="18" fill="#FFF9F0"/>';
    case 'function':
      return '<rect x="85" y="150" width="430" height="280" rx="30" fill="#1F2E40"/><rect x="130" y="210" width="150" height="34" rx="17" fill="#7BA3C8"/><rect x="130" y="270" width="240" height="24" rx="12" fill="#DCE9F3"/><rect x="130" y="315" width="200" height="24" rx="12" fill="#DCE9F3"/><path d="M380 210 L460 290 L380 370" stroke="#9FD4B3" stroke-width="18" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M220 410 C220 450 380 450 380 410" stroke="#F4C96D" stroke-width="14" fill="none" stroke-linecap="round"/>';
    case 'compiler':
      return '<rect x="75" y="135" width="450" height="310" rx="30" fill="#1F2E40"/><circle cx="120" cy="175" r="10" fill="#F3A683"/><circle cx="150" cy="175" r="10" fill="#F4C96D"/><circle cx="180" cy="175" r="10" fill="#9FD4B3"/><rect x="120" y="230" width="220" height="18" rx="9" fill="#7BA3C8"/><rect x="120" y="270" width="160" height="18" rx="9" fill="#DCE9F3"/><rect x="120" y="310" width="260" height="18" rx="9" fill="#DCE9F3"/><rect x="120" y="350" width="140" height="18" rx="9" fill="#9FD4B3"/><path d="M410 250 L445 285 L410 320" stroke="#F4C96D" stroke-width="14" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    case 'database':
      return '<ellipse cx="300" cy="170" rx="120" ry="36" fill="#7BA3C8"/><rect x="180" y="170" width="240" height="190" fill="#7BA3C8"/><ellipse cx="300" cy="360" rx="120" ry="36" fill="#5E86A8"/><ellipse cx="300" cy="170" rx="120" ry="36" fill="none" stroke="#3A4E6B" stroke-width="10"/><ellipse cx="300" cy="235" rx="120" ry="36" fill="none" stroke="#3A4E6B" stroke-width="10" opacity=".3"/><ellipse cx="300" cy="300" rx="120" ry="36" fill="none" stroke="#3A4E6B" stroke-width="10" opacity=".3"/><rect x="140" y="395" width="320" height="70" rx="24" fill="#FFF8EE"/><rect x="170" y="420" width="90" height="18" rx="9" fill="#C9D8E7"/><rect x="280" y="420" width="90" height="18" rx="9" fill="#C9D8E7"/>';
    case 'network':
      return '<rect x="96" y="122" width="160" height="110" rx="24" fill="#DCE9F3"/><rect x="344" y="122" width="160" height="110" rx="24" fill="#E8F4EA"/><rect x="220" y="332" width="160" height="110" rx="24" fill="#FFF1E8"/><rect x="140" y="158" width="72" height="12" rx="6" fill="#7BA3C8"/><rect x="388" y="158" width="72" height="12" rx="6" fill="#9FD4B3"/><rect x="264" y="368" width="72" height="12" rx="6" fill="#F3A683"/><path d="M256 177 L344 177" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M176 232 L268 332" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M424 232 L332 332" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><circle cx="300" cy="282" r="38" fill="#F4C96D"/><path d="M300 244 L300 320" stroke="#3A4E6B" stroke-width="10" stroke-linecap="round"/><path d="M262 282 L338 282" stroke="#3A4E6B" stroke-width="10" stroke-linecap="round"/>';
    case 'interface':
      return '<rect x="110" y="80" width="380" height="440" rx="36" fill="#1F2E40"/><rect x="130" y="120" width="340" height="340" rx="26" fill="#FFF8EE"/><rect x="130" y="120" width="340" height="56" rx="26" fill="#7BA3C8"/><circle cx="165" cy="148" r="8" fill="#F3A683"/><circle cx="190" cy="148" r="8" fill="#F4C96D"/><circle cx="215" cy="148" r="8" fill="#9FD4B3"/><rect x="156" y="210" width="104" height="104" rx="18" fill="#DCE9F3"/><circle cx="208" cy="245" r="18" fill="#7BA3C8"/><rect x="286" y="210" width="146" height="26" rx="13" fill="#9FD4B3"/><rect x="286" y="254" width="126" height="18" rx="9" fill="#C9D8E7"/><rect x="156" y="340" width="276" height="22" rx="11" fill="#F4C96D"/><rect x="156" y="384" width="210" height="18" rx="9" fill="#C9D8E7"/>';
    case 'recursion':
      return '<circle cx="300" cy="150" r="34" fill="#7BA3C8"/><circle cx="220" cy="255" r="30" fill="#9FD4B3"/><circle cx="380" cy="255" r="30" fill="#F4C96D"/><circle cx="170" cy="360" r="26" fill="#DCE9F3"/><circle cx="270" cy="360" r="26" fill="#F3A683"/><circle cx="330" cy="360" r="26" fill="#DCE9F3"/><circle cx="430" cy="360" r="26" fill="#F3A683"/><path d="M280 178 L238 228" stroke="#3A4E6B" stroke-width="10"/><path d="M320 178 L362 228" stroke="#3A4E6B" stroke-width="10"/><path d="M205 282 L181 336" stroke="#3A4E6B" stroke-width="10"/><path d="M235 282 L257 336" stroke="#3A4E6B" stroke-width="10"/><path d="M365 282 L341 336" stroke="#3A4E6B" stroke-width="10"/><path d="M395 282 L419 336" stroke="#3A4E6B" stroke-width="10"/>';
    case 'debugging':
      return '<rect x="90" y="130" width="420" height="320" rx="30" fill="#1F2E40"/><rect x="125" y="190" width="210" height="20" rx="10" fill="#7BA3C8"/><rect x="125" y="235" width="170" height="16" rx="8" fill="#DCE9F3"/><rect x="125" y="275" width="190" height="16" rx="8" fill="#DCE9F3"/><rect x="125" y="315" width="150" height="16" rx="8" fill="#F3A683"/><ellipse cx="395" cy="290" rx="54" ry="44" fill="#F4C96D"/><circle cx="378" cy="280" r="8" fill="#3A2A18"/><circle cx="412" cy="280" r="8" fill="#3A2A18"/><path d="M365 328 L340 350" stroke="#3A2A18" stroke-width="10" stroke-linecap="round"/><path d="M425 328 L450 350" stroke="#3A2A18" stroke-width="10" stroke-linecap="round"/>';
    case 'syntax':
      return '<rect x="90" y="135" width="420" height="310" rx="30" fill="#FFF8EE"/><path d="M180 220 L140 290 L180 360" stroke="#7BA3C8" stroke-width="18" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M420 220 L460 290 L420 360" stroke="#7BA3C8" stroke-width="18" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M305 205 L275 375" stroke="#F3A683" stroke-width="18" stroke-linecap="round"/><rect x="215" y="255" width="60" height="24" rx="12" fill="#9FD4B3"/><rect x="335" y="305" width="60" height="24" rx="12" fill="#F4C96D"/>';
    case 'framework':
      return '<rect x="110" y="120" width="380" height="340" rx="28" fill="#FFF8EE"/><rect x="145" y="155" width="140" height="110" rx="20" fill="#7BA3C8"/><rect x="315" y="155" width="140" height="110" rx="20" fill="#9FD4B3"/><rect x="145" y="295" width="140" height="110" rx="20" fill="#F4C96D"/><rect x="315" y="295" width="140" height="110" rx="20" fill="#F3A683"/><path d="M285 210 L315 210" stroke="#3A4E6B" stroke-width="12"/><path d="M285 350 L315 350" stroke="#3A4E6B" stroke-width="12"/><path d="M215 265 L215 295" stroke="#3A4E6B" stroke-width="12"/><path d="M385 265 L385 295" stroke="#3A4E6B" stroke-width="12"/>';
    case 'repository':
      return '<rect x="95" y="120" width="410" height="340" rx="30" fill="#FFF8EE"/><path d="M180 180 L180 360" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M180 230 C180 230 250 230 250 180 L250 150" stroke="#7BA3C8" stroke-width="12" fill="none" stroke-linecap="round"/><path d="M180 310 C180 310 340 310 340 250 L340 210" stroke="#9FD4B3" stroke-width="12" fill="none" stroke-linecap="round"/><circle cx="180" cy="180" r="20" fill="#F4C96D"/><circle cx="250" cy="150" r="20" fill="#F3A683"/><circle cx="180" cy="310" r="20" fill="#DCE9F3"/><circle cx="340" cy="210" r="20" fill="#7BA3C8"/><rect x="290" y="320" width="130" height="58" rx="18" fill="#1F2E40"/>';
    case 'encryption':
      return '<rect x="150" y="240" width="300" height="210" rx="34" fill="#7BA3C8"/><path d="M210 240 L210 190 C210 140 250 105 300 105 C350 105 390 140 390 190 L390 240" stroke="#F4C96D" stroke-width="22" fill="none" stroke-linecap="round"/><circle cx="300" cy="320" r="32" fill="#FFF9F0"/><rect x="288" y="345" width="24" height="50" rx="12" fill="#FFF9F0"/>';
    case 'array':
      return '<rect x="90" y="225" width="95" height="95" rx="20" fill="#7BA3C8"/><rect x="200" y="225" width="95" height="95" rx="20" fill="#9FD4B3"/><rect x="310" y="225" width="95" height="95" rx="20" fill="#F4C96D"/><rect x="420" y="225" width="95" height="95" rx="20" fill="#F3A683"/><rect x="90" y="345" width="425" height="54" rx="20" fill="#FFF8EE"/><circle cx="137" cy="372" r="12" fill="#FFF9F0"/><circle cx="247" cy="372" r="12" fill="#FFF9F0"/><circle cx="357" cy="372" r="12" fill="#FFF9F0"/><circle cx="467" cy="372" r="12" fill="#FFF9F0"/>';
    case 'backend':
      return '<rect x="70" y="155" width="140" height="230" rx="28" fill="#FFF8EE"/><rect x="108" y="185" width="64" height="16" rx="8" fill="#7BA3C8"/><rect x="108" y="222" width="64" height="16" rx="8" fill="#9FD4B3"/><rect x="108" y="259" width="64" height="16" rx="8" fill="#F4C96D"/><rect x="108" y="296" width="64" height="16" rx="8" fill="#F3A683"/><rect x="250" y="115" width="280" height="320" rx="30" fill="#1F2E40"/><rect x="285" y="155" width="210" height="42" rx="14" fill="#7BA3C8"/><text x="330" y="183" fill="#FFFFFF" font-size="24" font-family="Inter, Arial, sans-serif" font-weight="700">Server</text><rect x="290" y="230" width="196" height="20" rx="10" fill="#DCE9F3"/><rect x="290" y="270" width="150" height="18" rx="9" fill="#DCE9F3"/><rect x="290" y="308" width="176" height="18" rx="9" fill="#9FD4B3"/><rect x="290" y="346" width="132" height="18" rx="9" fill="#F4C96D"/><path d="M210 270 L250 270" stroke="#3A4E6B" stroke-width="12" stroke-linecap="round"/><path d="M228 255 L250 270 L228 285" stroke="#3A4E6B" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    case 'generic':
    default:
      return '<rect x="120" y="120" width="360" height="360" rx="30" fill="#FFF8EE"/><circle cx="300" cy="220" r="76" fill="#7BA3C8"/><rect x="200" y="330" width="200" height="26" rx="13" fill="#9FD4B3"/><rect x="160" y="380" width="280" height="20" rx="10" fill="#C9D8E7"/>';
  }
}
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" role="img" aria-labelledby="title desc">
  <title id="title"><?php echo h($word); ?></title>
  <desc id="desc"><?php echo h($scene['label']); ?></desc>
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#F8F1E5" />
      <stop offset="55%" stop-color="#E6EEF5" />
      <stop offset="100%" stop-color="#D4E3DA" />
    </linearGradient>
  </defs>
  <rect width="600" height="600" fill="url(#bg)" />
  <circle cx="485" cy="120" r="72" fill="#FFFFFF" opacity=".4" />
  <circle cx="95" cy="510" r="88" fill="#FFFFFF" opacity=".25" />
  <?php echo scene_shapes($scene['type']); ?>
</svg>
