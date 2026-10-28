<?php
function scoringGrid(int $rows, int $cols, array $endpoints): array
{
    $cells = [];
    for ($r = 1; $r <= $rows; $r++) for ($c = 1; $c <= $cols; $c++) {
        $cells["$r-$c"] = ['r' => $r, 'c' => $c, 'file' => 'OngXanh/OngXanh-ChuThap.svg', 'initialRot' => 0];
    }
    $pipes = [];
    foreach ($endpoints as $i => [$sr, $sc, $srot, $er, $ec, $erot]) {
        $id = $i + 1;
        $start = ['r' => $sr, 'c' => $sc, 'rot' => $srot];
        $end = ['r' => $er, 'c' => $ec, 'rot' => $erot];
        foreach ([['start', $start, 'BatDau'], ['end', $end, 'KetThuc']] as [$role, $pos, $shape]) {
            $cells[$pos['r'] . '-' . $pos['c']] = ['r' => $pos['r'], 'c' => $pos['c'],
                'file' => "Ong$shape.svg", 'initialRot' => $pos['rot'], 'pairId' => $id, 'role' => $role];
        }
        $pipes[] = ['pairId' => $id, 'start' => $start, 'end' => $end,
            'color' => ['hex' => '#00e5ff', 'name' => 'Test'], 'steps' => [], 'shortestLength' => 999];
    }
    return ['numPairs' => count($pipes), 'pipes' => $pipes, 'cells' => array_values($cells), 'thinkingTimeMs' => 300000];
}
function replaceScoringCell(array $map, int $r, int $c, string $file, int $rotation): array
{
    foreach ($map['cells'] as &$cell) if ($cell['r'] === $r && $cell['c'] === $c) {
        $cell['file'] = $file; $cell['initialRot'] = $rotation;
    }
    return $map;
}
