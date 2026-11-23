<?php
// Statistics Graph — originally by Gendoh http://gendoh.com (server-side jpgraph PNG).
// Reimplemented as a dependency-free inline SVG line chart.
// The bundled jpgraph 1.x (QPL license) is no longer used; see SECURITY.md.
// Data: Statistics::getWeeklyStatistics() — last 8 days of daily visits.

function DisplayStatisticsGraph($target)
{
	requireComponent('Textcube.Model.Statistics');
	$rows = Statistics::getWeeklyStatistics();
	if (!is_array($rows)) $rows = array();
	$rows = array_reverse($rows);

	$pos = 0; $xdata = array(); $ydata = array();
	for ($i = 7; $i >= 0; $i--) {
		$week = strtotime("-{$i} day");
		$xdata[] = date('d', $week);
		if (!isset($rows[$pos]) || (date('d', $week) != substr($rows[$pos]['datemark'], -2))) {
			$ydata[] = 0;
		} else {
			$ydata[] = (int)$rows[$pos++]['visits'];
		}
	}

	// --- dependency-free SVG line chart (replaces QPL jpgraph) ---
	$W = 175; $H = 120; $padL = 16; $padR = 8; $padT = 10; $padB = 18;
	$n = count($ydata);
	$max = 1; foreach ($ydata as $v) { if ($v > $max) $max = $v; }
	$plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
	$baseY = $padT + $plotH;

	$pts = array(); $marks = ''; $xlabels = '';
	for ($i = 0; $i < $n; $i++) {
		$x = $padL + ($n <= 1 ? 0 : $plotW * $i / ($n - 1));
		$y = $padT + $plotH * (1 - $ydata[$i] / $max);
		$xr = round($x, 1); $yr = round($y, 1);
		$pts[] = $xr . ',' . $yr;
		$marks .= '<circle cx="' . $xr . '" cy="' . $yr . '" r="1.6" fill="red" />';
		if ($ydata[$i] > 0) {
			$marks .= '<text x="' . $xr . '" y="' . round($yr - 3, 1) . '" font-size="7" fill="#777" text-anchor="middle">' . (int)$ydata[$i] . '</text>';
		}
		$xlabels .= '<text x="' . $xr . '" y="' . ($H - 6) . '" font-size="7" fill="#999" text-anchor="middle">' . htmlspecialchars($xdata[$i], ENT_QUOTES) . '</text>';
	}
	$poly = implode(' ', $pts);
	$nowLabel = htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES);

	$svg =
		'<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $H . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Blog Visitors (weekly)">' .
		'<rect x="0" y="0" width="' . $W . '" height="' . $H . '" fill="#ffffff" />' .
		'<line x1="' . $padL . '" y1="' . $baseY . '" x2="' . ($W - $padR) . '" y2="' . $baseY . '" stroke="#dddddd" stroke-width="1" />' .
		'<text x="2" y="' . ($padT + 6) . '" font-size="7" fill="#999999">Hits</text>' .
		'<polyline points="' . $poly . '" fill="none" stroke="#666666" stroke-width="1" />' .
		$marks . $xlabels .
		'<title>Blog Visitors — ' . $nowLabel . '</title>' .
		'</svg>';

	return '<div style="overflow:hidden; width:100%; text-align:center">' . $svg . '</div>';
}
?>
