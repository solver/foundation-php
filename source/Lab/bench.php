<?php
namespace Solver\Lab;

class Bench {
	protected $baseline;
	protected $functions = [];
	protected $labelsIndex = [];
	protected $cli;
	
	public function __construct($cli = false) {
		$this->cli = $cli;
	}

	// The baseline serves to measure the overhead of running the benchmark and whatever setup all other tests do, which
	// is immaterial to the results. If not provided, Bench runs an empty closure as the baseline.
	public function setBaseline(\Closure $function) {
		$this->baseline = $function;
	}
	
	public function add($label, \Closure $function) {
		$baselineLabel = $this->getBaselineLabel();
		
		if (isset($this->labelsIndex[$label])) throw new \Exception('Test label "' . $label . '" was used twice or more.');
		if (trim(strtolower($label)) === strtolower($baselineLabel)) throw new \Exception('Test label "' . $baselineLabel . '" is reserved.');
		$this->labelsIndex[$label] = true;
		$this->functions[] = [$label, $function];
	}
	
	// Prints test outputs, grouping those that match.
	public function dump() {		
		$functions = $this->getFunctions();
		$baselineLabel = $this->getBaselineLabel();
		
		$dumps = [];
		
		foreach ($functions as list($label, $function)) {
			ob_start();
			var_dump($function());
			$out = ob_get_clean();
			
			if (!isset($dumps[$out])) $dumps[$out] = [];
			$dumps[$out][] = $label;
		}
		
		foreach ($dumps as $out => $labels) {
			echo str_repeat('-', 80) . "\n";
			echo implode("\n", $labels) . "\n";
			echo str_repeat('-', 80) . "\n";
			
			echo $out;
		}
		
		echo str_repeat('-', 80) . "\n";
		echo "DONE\n";
		echo str_repeat('-', 80) . "\n";
	}
	
	// Runs a benchmark.
	public function bench($secPerTest = 0.1, $maxRounds = 1000, $warmupRounds = 3) {
		// FIXME: Buggy when warmupRounds = 1, the logic there is crap anyway, refactor.
		$functions = $this->getFunctions();
		$baselineLabel = $this->getBaselineLabel();
		
		$secPerRound = $secPerTest * count($functions);
		
		$stats = [];
		$cycles = 0;
		$ramping = true;
		foreach ($functions as list($label, $function)) {
			$stats[$label] = 0;
		}
		$resetStats = $stats;
		
		$cyclesPerRound = 1;
		$round = -$warmupRounds;
		for ($round = -$warmupRounds; $maxRounds === null || $maxRounds === 0 || $round <= $maxRounds; $round++) {
			// Prep.
			if ($round == 0) continue;
			if ($round <= 1) {
				$stats = $resetStats;			
				$cycles = 0;
			}
			
			shuffle($functions); // To avoid bias of running tests in same order (which may skew their results).
			
			// Test.
			$roundTime1 = microtime(1);				
			foreach ($functions as list($label, $function)) {
				$time1 = microtime(1);				
				for ($i = $cyclesPerRound; $i--;) $function();				
				$time2 = microtime(1);
				
				$delta = $time2 - $time1;
				$stats[$label] += $delta;
			}
			$cycles += $cyclesPerRound;
			$roundTime2 = microtime(1);
			
			// Print.	
			// TODO: HTML support? Machine data export?
			ob_start();
			echo "\n" . str_repeat('_', 80) . "\n\n";
			if ($ramping) {
				echo 'Ramping up... @ ' . number_format($cyclesPerRound, 0, '.', ',') . " cycles per test / round\n";
			} else {
				if ($round < 1) {
					echo 'Warmup #' . -$round . " @ " . number_format($cyclesPerRound, 0, '.', ',') . " cycles per test / round\n";
				} else {
					echo 'Round #' . $round . " / "  . ($maxRounds === null ? 'unlimited' : number_format($maxRounds, 0, '.', ',')) . ' @ ' . number_format($cyclesPerRound, 0, '.', ',') . " cycles per test (" . number_format($cycles, 0, '.', ',') . " total)\n";
				}
			}
			echo "\n\n";
			
			$this->printStats($stats, $baselineLabel, $round >= 1 ? $cycles : $cyclesPerRound);
			
			ob_end_flush();
			
			// Adjust
			$roundDelta = $roundTime2 - $roundTime1;
			
			if ($ramping) {
				if ($roundDelta < $secPerRound) {
					if ($cyclesPerRound < 10) {
						$cyclesPerRound++;
					} else {
						$cyclesPerRound = (int) ($cyclesPerRound * 1.5);
					}
					
					// Holding on to warming up rounds until we ramp-up.
					if ($round < 1) {
						$round = - 2 - $warmupRounds;
					}
				} else {
					$ramping = false;
				}
			} else {
				$cyclesPerRound = (int) (max(1, $secPerRound * $cyclesPerRound / $roundDelta) * 0.2 + $cyclesPerRound * 0.8);
			}
		}
	}
	
	protected function printStats($stats, $baselineLabel, $cycles) {
		$nl = "\n";
		
		$fixedPrint = function ($leftText, $rightText, $minLength, $padChar, $minPadding = 3) {
			if ($leftText == '' || $rightText == '') $minPadding = 0;
			
			$leftLength = strlen($leftText);
			$rightLength = strlen($rightText);
			$len = max($minLength, $leftLength + $rightLength + $minPadding);
			echo $leftText . str_repeat($padChar, $len - $leftLength - $rightLength) . $rightText;
		};
		
		$baselineTimeDelta = $stats[$baselineLabel];
		
		$minTime = 60 * 60 * 24 * 365;
		$maxTime = 0;
				
		foreach ($stats as $label => $timeDelta) {
			$minTime = min($minTime, $timeDelta);
			$maxTime = max($maxTime, $timeDelta);
		}
		
		$rangeTime = $maxTime - $minTime;
		
		$rangeColLength = 10;
		$cyclesColLength = 12;
		$msecColLength = 18;
		$msecNoBaseColLength = 16;
		
		$printCol = function ($range = '-', $cycles = '-', $msec = '-', $msecNoBase = '-') use ($fixedPrint, $rangeColLength, $cyclesColLength, $msecColLength, $msecNoBaseColLength) {
			if (($range === '-' || $range === '=') && $cycles === '-' && $msec === '-' && $msecNoBase === '-') $rule = true; else $rule = false;
			
			if ($rule) {
				echo '+' . $range;
				echo str_repeat($range, $rangeColLength);
				echo $range . '+' . $range;
				echo str_repeat($range, $cyclesColLength);
				echo $range . '+' . $range;
				echo str_repeat($range, $msecColLength);
				echo $range . '+' . $range;
				echo str_repeat($range, $msecNoBaseColLength);
				echo $range . '+';
			} else {
				echo '| ';
				$fixedPrint('', $range, $rangeColLength, ' ');
				echo ' | ';
				$fixedPrint('', $cycles, $cyclesColLength, ' ');
				echo ' | ';
				$fixedPrint('', $msec, $msecColLength, ' ');
				echo ' | ';
				$fixedPrint('', $msecNoBase, $msecNoBaseColLength, ' ');
				echo ' |';
			}
			echo "\n";			
		};
		
		// TODO: Make optional.
		asort($stats);
		
		$printCol('=');
		$printCol('Min...max', 'Cycles/sec', 'Time / 1M cycles', '& w/o baseline');
		$printCol('=');
		
		foreach ($stats as $label => $timeDelta) {
			echo $nl;
			//echo '| ';
			$fixedPrint($label, '', ' ', 100);			
			//echo ' |';
			echo $nl;
			
			$printCol();
			
			$printCol(
				round(100 * ($timeDelta - $minTime) / $rangeTime) . ' %',
				number_format((int) ($cycles / $timeDelta), 0, '.', ','),
				number_format(1000000000 * ($timeDelta) / $cycles, 3, '.', ',') . ' ms',
				number_format(1000000000 * ($timeDelta - $baselineTimeDelta) / $cycles, 3, '.', ',') . ' ms'
			);
			$printCol();
		}
	}
	
	protected function getBaselineLabel() {
		return 'BASELINE';
	}
	
	protected function getFunctions() {
		$baseline = $this->baseline ?: function () {};
		$baselineLabel = $this->getBaselineLabel();
		
		$functions = $this->functions;
		array_unshift($functions, [$baselineLabel, $baseline]);
		
		return $functions;
	}
}