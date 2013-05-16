<?php

/*
 * @desc 分页程序
 * @params $num      总记录数
 * @params $perpage  每页行数
 * @params $curpage  当前页
 * @params $mpurl    URL地址
 * @params $maxpages 显示分页长度
 */
function multi($num, $perpage, $curpage, $mpurl, $maxpages = 0) {
	$multipage = '';
	$mpurl .= strpos($mpurl, '?')!==false ? '&amp;' : '?';
	
	if($num > $perpage) {
		$page = $maxpages ? $maxpages : 5;
		$offset = 2;

		$realpages = @ceil($num / $perpage);

		if($page > $realpages) {
			$from = 1;
			$to = $realpages;
		} else {
			$from = $curpage - $offset;
			$to = $from + $page - 1;
			if($from < 1) {
				$to = $curpage + 1 - $from;
				$from = 1;
				if($to - $from < $page) {
					$to = $page;
				}
			} elseif($to > $realpages) {
				$from = $realpages - $page + 1;
				$to = $realpages;
			}
		}

        $multipage = '<div class="pagination pagination-centered"><ul>';
        $multipage .= ($curpage - $offset > 1 && $realpages > $page ? '<li><a href="'.$mpurl.'page=1">首页</a></li>' : '').
            ($curpage > 1 ? '<li><a href="'.$mpurl.'page='.($curpage - 1).'">上一页</a></li>' : '');

        for($i = $from; $i <= $to; $i++)
        {
			$multipage .= $i == $curpage ? '<li class="active"><a>'.$i.'</a></li>' :
				'<li><a href="'.$mpurl.'page='.$i.'" >'.$i.'</a></li>';
		}

		$multipage .= ($curpage < $realpages ? '<li><a href="'.$mpurl.'page='.($curpage + 1).'">下一页</a></li>' : '').
			($to < $realpages ? '<li><a href="'.$mpurl.'page='.$realpages.'">末页</a>' : '');
//            .($realpages > $page ? '<a class="p_pages" style="padding: 0px"><input class="p_input" type="text" name="custompage" onKeyDown="if(event.keyCode==13) {window.location=\''.$mpurl.'page=\'+this.value; return false;}"></a>' : '');

//		$multipage = $multipage ? '<div class="p_bar"><a class="p_total">&nbsp;'.$num.'&nbsp;</a><a class="p_pages">&nbsp;'.$curpage.'/'.$realpages.'&nbsp;</a>'.$multipage.'</div>' : '';
        $multipage .= '</ul></div>';
	}
	return $multipage;
}


?>