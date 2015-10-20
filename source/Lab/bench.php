<?php
namespace Solver\Lab;

class Bench {
	protected $empty;
	protected $functions = [];
	protected $cli;
	
	public function __construct($cli = false) {
		$this->cli = $cli;
	}

	public function setBaseline(\Closure $function) {
		$this->empty = $function;
	}
	
	public function add($label, \Closure $function) {
		$this->functions[] = [$label, $function];
	}
		
	public function scramble() {
		shuffle($this->functions);
	}
	
	public function run($count) {
		$function = $this->empty ? $this->empty : function () {};
		
		$stats = $this->getStats();
		for ($i = $count; $i--;) $function();
		$stats2 = $this->getStats();
		
		$timeDiff = $stats2[4] - $stats[4];
		
		$this->printStats('Baseline test (delta time subtracted from subsequent tests)', $stats, $stats2, 0);
			
		// TODO: Split runs in pieces and interleave, to avoid bias with first/last runner.
		foreach ($this->functions as list($label, $function)) {
			$stats = $this->getStats();
			
			for ($i = $count; $i--;) $function();
			
			$stats2 = $this->getStats();
			$this->printStats($label, $stats, $stats2, $timeDiff);
		}
	}
	
	protected function getStats() {
		return [memory_get_usage(), memory_get_usage(true), memory_get_peak_usage(), memory_get_peak_usage(true), microtime(1)];
	}
	
	protected function printStats($label, $stats1, $stats2, $timeDiff) {
		list($mem, $memReal, $memPeak, $memRealPeak, $time) = $stats1;
		list($memNew, $memRealNew, $memPeakNew, $memRealPeakNew, $timeNew) = $stats2;
		
		$nl = "\n";
		
		$labelLength = 20;
		$outputsLength = 15;
		
		// TODO: Better CLI support. Machine data export.
		if ($this->cli) {
			ob_start();
		}
		
		echo '<p><b>' . $label . '</b></p>' . $nl;
		echo '<code><p>' . $nl;
		/*
		Doesn't seem to produce interesting info as the memory is freed between invocations. Think about how to measure this.
		
		echo str_pad('Memory', $labelLength, '.', STR_PAD_RIGHT);		
		echo str_pad(number_format(($memNew - $mem) >> 10, 0), $outputsLength, '.', STR_PAD_LEFT) . ' kb<br>' . $nl;
		echo str_pad('Memory Real', $labelLength, '.', STR_PAD_RIGHT);
		echo str_pad(number_format(($memRealNew - $memReal) >> 10, 0), $outputsLength, '.', STR_PAD_LEFT) . ' kb<br>' . $nl;
		echo str_pad('Memory Peak', $labelLength, '.', STR_PAD_RIGHT);
		echo str_pad(number_format(($memPeakNew - $memPeak) >> 10, 0), $outputsLength, '.', STR_PAD_LEFT) . ' kb<br>' . $nl;
		echo str_pad('Memory Real Peak', $labelLength, '.', STR_PAD_RIGHT);
		echo str_pad(number_format(($memRealPeakNew - $memRealPeak) >> 10, 0), $outputsLength, '.', STR_PAD_LEFT) . ' kb<br>' . $nl;
		*/
		echo str_pad('Time', $labelLength, '.', STR_PAD_RIGHT);
		echo str_pad(number_format($timeNew - $time, 5), $outputsLength, '.', STR_PAD_LEFT);
	
		if ($timeDiff != 0) {
			echo ' sec      ';
			echo str_pad('Time - Baseline', $labelLength, '.', STR_PAD_RIGHT);
			echo str_pad(number_format($timeNew - $time - $timeDiff, 5), $outputsLength, '.', STR_PAD_LEFT);
		}
		
		echo ' sec' . $nl . '</code></p>' . $nl . $nl;
		
		if ($this->cli) {
			echo strip_tags(ob_get_clean());
		}
	}
}