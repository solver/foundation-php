<?php
/*
 * Copyright (C) 2006-2013 Solver Ltd. All rights reserved.
 * 
 * All information contained herein is, and remains the property of Solver Limited. The intellectual and technical
 * concepts contained herein are proprietary to Solver Limited and may be covered by patents, patents in process, and
 * are protected by trade secret and copyright law. Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained from Solver Limited.
 * 
 * Authors:
 * 
 * Stan Vass (stan.vass@solver.bg)
 */
namespace Solver\Lab;

class DammChecksum
{	
	/**
	 * Returns the Damm checksum digit for an input of digits.
	 * 
	 * @param string $input
	 * A string of digits. Non-digit characters will be ignored.
	 * 
	 * @return string
	 * A checksum digit 0-9.
	 */
	public static function getChecksum($input)
	{
		return self::getChecksumInternal($input);
	}
	
	/**
	 * Checks the integrity of a sequence of decimal digits ending with a Damm checksum digit.
	 * 
	 * @param string $input
	 * A string of digits. Non-digit characters will be ignored. The last digit should be the checksum.
	 * 
	 * @return bool
	 * True if the checksum matches, false if it doesn't.
	 */
	public static function verifyCheckum($input)
	{
		return (bool) (self::getChecksumInternal($input) === '0');
	}
	
	private static function getChecksumInternal($input)
	{
		// Totally anti-symmetric quasigroup from Damm's dissertation.
		static $damm = [
			'0317598642',
			'7092154863',
			'4206871359',
			'1750983426',
			'6123045978',
			'3674209581',
			'5869720134',
			'8945362017',
			'9438617205',
			'2581436790',
		];
		
		$input = \preg_replace('/[^\d]+/', '', $input);
		
		$interim = 0;
		for ($i = 0, $m = \strlen($input); $i < $m; $i++) {
			$interim = $damm[(int) $interim][(int) $input[$i]];
		}
		
		return $interim;
	}
	
}