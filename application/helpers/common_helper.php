<?php

//截取中文字符串

function FSubstr($title,$start=0,$len=8,$magic=true)
{
  /**
  *  powered by Smartpig
  *  mailto:d.einstein@263.net
  */

	if($len == "") $len=strlen($title);
	 
	if($start != 0)
	{
		$startv = ord(substr($title,$start,1));
		if($startv >= 128)
		{
			if($startv < 192)
			{
				for($i=$start-1;$i>0;$i--)
				{
					$tempv = ord(substr($title,$i,1));
					if($tempv >= 192) break;
				}
				$start = $i;
			}
		}
	}
 
	if(strlen($title)<=$len) return substr($title,$start,$len);
 
	$alen   = 0;
	$blen = 0;

	$realnum = 0;
 
	$length = 0;
	for($i=$start;$i<strlen($title);$i++)
	{
		$ctype = 0;
		$cstep = 0;
 
		$cur = substr($title,$i,1);
		if($cur == "&")
		{
			if(substr($title,$i,4) == "&lt;")
			{
				$cstep = 4;
				$length += 4;
				$i += 3;
				$realnum ++;
				if($magic)
				{
					$alen ++;
				}
			}
			else if(substr($title,$i,4) == "&gt;")
			{
				$cstep = 4;
				$length += 4;
				$i += 3;
				$realnum ++;
				if($magic)
				{
					$alen ++;
				}
			}
			else if(substr($title,$i,5) == "&amp;")
			{
				$cstep = 5;
				$length += 5;
				$i += 4;
				$realnum ++;
				if($magic)
				{
					$alen ++;
				}
			}
			else if(substr($title,$i,6) == "&quot;")
			{
				$cstep = 6;
				$length += 6;
				$i += 5;
				$realnum ++;
				if($magic)
				{
					$alen ++;
				}
			}
			else if(preg_match("/&#(\d+);?/i",substr($title,$i,8),$match))
			{
				$cstep = strlen($match[0]);
				$length += strlen($match[0]);
				$i += strlen($match[0])-1;
				$realnum ++;
				if($magic)
				{
					$blen ++;
					$ctype = 1;
				}
			}
		}
		else
		{
			if(ord($cur)>=252)
			{
				$cstep = 6;
				$length += 6;
				$i += 5;
				$realnum ++;
				if($magic)
				{
					$blen ++;
					$ctype = 1;
				}
			}
			elseif(ord($cur)>=248)
			{
				$cstep = 5;
				$length += 5;
				$i += 4;
				$realnum ++;
				if($magic)
				{
					$ctype = 1;
					$blen ++;
				}
			}
			elseif(ord($cur)>=240)
			{
				$cstep = 4;
				$length += 4;
				$i += 3;
				$realnum ++;
				if($magic)
				{
					$blen ++;
					$ctype = 1;
				}
			}
			elseif(ord($cur)>=224)
			{
				$cstep = 3;
				$length += 3;
				$i += 2;
				$realnum ++;
				if($magic)
				{
					$ctype = 1;
					$blen ++;
				}
			}
			elseif(ord($cur)>=192)
			{
				$cstep = 2;
				$length += 2;
				$i += 1;
				$realnum ++;
				if($magic)
				{
					$blen ++;
					$ctype = 1;
				}
			}
			elseif(ord($cur)>=128)
			{
				$length += 1;
			}
			else
			{
				$cstep = 1;
				$length +=1;
				$realnum ++;
				if($magic)
				{
					if(ord($cur) >= 65 && ord($cur) <= 90)
					{
						$blen++;
					}
					else
					{
						$alen++;
					}
				}
			}
		}
 
 
		if($magic)
		{
			if(($blen*2+$alen) == ($len*2)) break;
			if(($blen*2+$alen) == ($len*2+1))
			{
				if($ctype == 1)
				{
					$length -= $cstep;
					break;
				}
				else
				{
					break;
				}
			}
		}
		else
		{
			if($realnum == $len) break;
		}
	}
 
	unset($cur);
	unset($alen);
	unset($blen);
	unset($realnum);
	unset($ctype);
	unset($cstep);
 
	if ($length < strlen($title))
		return substr($title,$start,$length).'...';
	else
		return substr($title,$start,$length);
}


?>