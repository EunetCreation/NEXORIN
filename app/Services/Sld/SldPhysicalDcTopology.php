<?php

namespace App\Services\Sld;

/**
 * Canonical physical DC topology for calculation-controlled PV strings.
 *
 * The service does not calculate string allocation. It normalizes the
 * Technical Calculation assignment into deterministic graph identities,
 * physical polarity terminals, and inverter MPPT/input terminals.
 */
final class SldPhysicalDcTopology
{
    public const VERSION = 'SLD-REF-001';

    /**
     * @param array<int, array<string, mixed>> $strings
     * @return array<int, array<string, mixed>>
     */
    public function normalizeStrings(array $strings, int $inverterIndex): array
    {
        $inputCounters = [];
        $normalized = [];

        foreach (array_values(array_filter($strings, 'is_array')) as $offset => $string) {
            $stringIndex = max(1, (int) ($string['string_index'] ?? ($offset + 1)));
            $mpptIndex = max(1, (int) ($string['mppt_index'] ?? ($offset + 1)));
            $inputCounters[$mpptIndex] = ($inputCounters[$mpptIndex] ?? 0) + 1;
            $inputIndex = $inputCounters[$mpptIndex];
            $channelIndex = $offset + 1;
            $tag = trim((string) ($string['tag'] ?? '')) ?: sprintf('STR-%02d', $stringIndex);

            $normalized[] = array_replace($string, [
                'string_index' => $stringIndex,
                'tag' => $tag,
                'inverter_index' => $inverterIndex,
                'channel_index' => $channelIndex,
                'channel_label' => sprintf('CH-%02d', $channelIndex),
                'mppt_index' => $mpptIndex,
                'mppt_label' => trim((string) ($string['mppt_label'] ?? '')) ?: sprintf('MPPT-%02d', $mpptIndex),
                'input_index' => $inputIndex,
                'input_label' => sprintf('IN-%02d', $inputIndex),
                'node_id' => sprintf('pv_string_%03d', $stringIndex),
                'isolator_ports' => $this->isolatorPortIds($channelIndex),
                'inverter_ports' => $this->inverterPortIds($mpptIndex, $inputIndex),
            ]);
        }

        return $normalized;
    }

    /** @return array<string, string> */
    public function isolatorPortIds(int $channelIndex): array
    {
        if ($channelIndex === 1) {
            return [
                'positive_input' => 'DC_IN_POS',
                'negative_input' => 'DC_IN_NEG',
                'positive_output' => 'DC_OUT_POS',
                'negative_output' => 'DC_OUT_NEG',
            ];
        }

        $prefix = sprintf('CH%02d', $channelIndex);

        return [
            'positive_input' => "{$prefix}_DC_IN_POS",
            'negative_input' => "{$prefix}_DC_IN_NEG",
            'positive_output' => "{$prefix}_DC_OUT_POS",
            'negative_output' => "{$prefix}_DC_OUT_NEG",
        ];
    }

    /** @return array<string, string> */
    public function inverterPortIds(int $mpptIndex, int $inputIndex): array
    {
        if ($mpptIndex === 1 && $inputIndex === 1) {
            return [
                'positive' => 'DC_MPPT1_POS',
                'negative' => 'DC_MPPT1_NEG',
            ];
        }

        $prefix = sprintf('DC_MPPT%02d_IN%02d', $mpptIndex, $inputIndex);

        return [
            'positive' => "{$prefix}_POS",
            'negative' => "{$prefix}_NEG",
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $strings
     * @return array<int, array<string, mixed>>
     */
    public function isolatorPorts(array $strings): array
    {
        $ports = [];
        $count = max(count($strings), 1);

        foreach ($strings as $offset => $string) {
            $center = $this->channelAnchor($offset, $count);
            $ids = is_array($string['isolator_ports'] ?? null)
                ? $string['isolator_ports']
                : $this->isolatorPortIds($offset + 1);
            $tag = (string) ($string['tag'] ?? sprintf('STR-%02d', $offset + 1));

            $ports[] = $this->port((string) $ids['negative_input'], "{$tag} negative input", 'dc_negative', 'input', 'left', max(0.04, $center - 0.035), 'power_dc_negative');
            $ports[] = $this->port((string) $ids['positive_input'], "{$tag} positive input", 'dc_positive', 'input', 'left', min(0.96, $center + 0.035), 'power_dc_positive');
            $ports[] = $this->port((string) $ids['negative_output'], "{$tag} negative output", 'dc_negative', 'output', 'right', max(0.04, $center - 0.035), 'power_dc_negative');
            $ports[] = $this->port((string) $ids['positive_output'], "{$tag} positive output", 'dc_positive', 'output', 'right', min(0.96, $center + 0.035), 'power_dc_positive');
        }

        $ports[] = [
            'id' => 'SPD_TAP',
            'label' => 'DC SPD shunt tap',
            'network' => 'dc_power',
            'direction' => 'output',
            'side' => 'bottom',
            'terminal_class' => 'power_dc',
            'anchor' => ['x' => 0.5, 'y' => 1.0],
            'required' => false,
            'max_connections' => 1,
        ];

        return $ports;
    }

    /**
     * @param array<int, array<string, mixed>> $strings
     * @return array<int, array<string, mixed>>
     */
    public function inverterPorts(array $strings, int $availableMppt, string $systemType, array $baseSourcePorts): array
    {
        $ports = [];
        $stringsByMppt = [];
        foreach ($strings as $string) {
            $stringsByMppt[(int) ($string['mppt_index'] ?? 1)][] = $string;
        }

        $mpptCount = max($availableMppt, $stringsByMppt === [] ? 1 : max(array_keys($stringsByMppt)));
        for ($mpptIndex = 1; $mpptIndex <= $mpptCount; $mpptIndex++) {
            $inputs = $stringsByMppt[$mpptIndex] ?? [[
                'mppt_index' => $mpptIndex,
                'input_index' => 1,
                'tag' => 'UNASSIGNED',
                'inverter_ports' => $this->inverterPortIds($mpptIndex, 1),
            ]];
            foreach (array_values($inputs) as $inputOffset => $string) {
                $inputIndex = max(1, (int) ($string['input_index'] ?? ($inputOffset + 1)));
                $ids = is_array($string['inverter_ports'] ?? null)
                    ? $string['inverter_ports']
                    : $this->inverterPortIds($mpptIndex, $inputIndex);
                $anchor = $this->mpptAnchor($mpptIndex, $mpptCount, $inputIndex, count($inputs));
                $label = sprintf('MPPT %d input %d', $mpptIndex, $inputIndex);

                $ports[] = $this->port((string) $ids['negative'], "{$label} negative", 'dc_negative', 'input', 'left', max(0.035, $anchor - 0.025), 'power_dc_negative');
                $ports[] = $this->port((string) $ids['positive'], "{$label} positive", 'dc_positive', 'input', 'left', min(0.965, $anchor + 0.025), 'power_dc_positive');
            }
        }

        foreach ($baseSourcePorts as $port) {
            if (! is_array($port)) {
                continue;
            }
            $id = (string) ($port['id'] ?? '');
            if (in_array($id, ['DC_IN'], true)) {
                continue;
            }
            if ($systemType !== 'hybrid' && in_array($id, ['BAT_DC', 'AC_BACKUP'], true)) {
                continue;
            }
            $ports[] = $port;
        }

        $ports[] = [
            'id' => 'CTRL',
            'label' => 'Active-power limit input',
            'network' => 'control',
            'direction' => 'input',
            'side' => 'bottom',
            'terminal_class' => 'control_input',
            'anchor' => ['x' => 0.82, 'y' => 1.0],
            'required' => false,
            'max_connections' => 1,
        ];

        return $ports;
    }

    public function nodeHeight(int $channelCount): float
    {
        return max(120.0, 56.0 + (max(1, $channelCount) * 26.0));
    }

    private function channelAnchor(int $offset, int $count): float
    {
        return ($offset + 1) / ($count + 1);
    }

    private function mpptAnchor(int $mpptIndex, int $mpptCount, int $inputIndex, int $inputCount): float
    {
        $mpptCenter = $mpptIndex / ($mpptCount + 1);
        if ($inputCount <= 1) {
            return $mpptCenter;
        }

        $spread = min(0.06, 0.22 / max($mpptCount, 1));
        $relative = ($inputIndex - (($inputCount + 1) / 2)) / max($inputCount, 1);

        return min(0.94, max(0.06, $mpptCenter + ($relative * $spread)));
    }

    /** @return array<string, mixed> */
    private function port(string $id, string $label, string $network, string $direction, string $side, float $y, string $terminalClass): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'network' => $network,
            'direction' => $direction,
            'side' => $side,
            'terminal_class' => $terminalClass,
            'anchor' => ['x' => $side === 'left' ? 0.0 : 1.0, 'y' => round($y, 5)],
            'required' => false,
            'max_connections' => 1,
        ];
    }
}
