<?php
namespace Solver\Lab;

function bench($label = null)
{	
	static $mem, $memReal, $memPeak, $memRealPeak, $time, $first = true;
	
	if (!$first) {
		$memNew = memory_get_usage();
		$memRealNew = memory_get_usage(true);
		$memPeakNew = memory_get_peak_usage();
		$memRealPeakNew = memory_get_peak_usage(true);
		$timeNew = microtime(1);
		
		if ($label) {
			$labelLength = 20;
			$resultsLength = 15;
			echo '<p><b>' . $label . ':</b></p>';
			echo '<code><p>';
			echo str_pad('Memory', $labelLength, '.', STR_PAD_RIGHT);		
			echo str_pad(number_format($memNew - $mem, 0), $resultsLength, '.', STR_PAD_LEFT) . '<br>';
			echo str_pad('Memory Real', $labelLength, '.', STR_PAD_RIGHT);
			echo str_pad(number_format($memRealNew - $memReal, 0), $resultsLength, '.', STR_PAD_LEFT) . '<br>';
			echo str_pad('Memory Peak', $labelLength, '.', STR_PAD_RIGHT);
			echo str_pad(number_format($memPeakNew - $memPeak, 0), $resultsLength, '.', STR_PAD_LEFT) . '<br>';
			echo str_pad('Memory Real Peak', $labelLength, '.', STR_PAD_RIGHT);
			echo str_pad(number_format($memRealPeakNew - $memRealPeak, 0), $resultsLength, '.', STR_PAD_LEFT) . '<br>';
			echo str_pad('Time', $labelLength, '.', STR_PAD_RIGHT);
			echo str_pad(number_format($timeNew - $time, 5), $resultsLength, '.', STR_PAD_LEFT);
			echo '</code></p>';
		}
		
		$mem = $memNew;
		$memReal = $memRealNew;
		$memPeak = $memPeakNew;
		$memRealPeak = $memRealPeakNew;
		$time = $timeNew;
	} else {
		$first = false;
		
		$mem = memory_get_usage();
		$memReal = memory_get_usage(true);
		$memPeak = memory_get_peak_usage();
		$memRealPeak = memory_get_peak_usage(true);
		$time = microtime(1);
	}
}