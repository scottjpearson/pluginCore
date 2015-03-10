<?php
function getRandomIdentifier($length = 6) {
	$output = "";
	$startNum = pow(32,5) + 1;
	$endNum = pow(32,6);
	while($length > 0) {

		# Generate a number between 32^5 and 32^6, then convert to a 6 digit string
		$randNum = mt_rand($startNum,$endNum);
		$randAlphaNum = numberToBase($randNum,32);

		if($length >= 6) {
			$output .= $randAlphaNum;
		}
		else {
			$output .= substr($randAlphaNum,0,$length);
		}
		$length -= 6;
	}

	return $output;
}

function numberToBase($number, $base) {
	$newString = "";
	while($number > 0) {
		$lastDigit = $number % $base;
		$newString = convertDigit($lastDigit, $base).$newString;
		$number -= $lastDigit;
		$number /= $base;
	}

	return $newString;
}

function convertDigit($number, $base) {
	if($base > 192) {
		chr($number);
	}
	else if($base == 32) {
		$stringArray = "ABCDEFGHJLKMNPQRSTUVWXYZ23456789";

		return substr($stringArray,$number,1);
	}
	else {
		if($number < 192) {
			return chr($number + 32);
		}
		else {
			return "";
		}
	}
}