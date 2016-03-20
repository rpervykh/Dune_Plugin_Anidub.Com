<?php
    
define('HOST_API_URL',  'http://online.anidub.com');
    function str_remove_spec($str)
    {
        $in_array = array
        (
            chr(9),
            chr(10),
            chr(13)
        );

        $str = str_replace($in_array, "", $str);
        $str = str_replace("<br>", "", $str);
        $str = str_replace("</br>", "", $str);
        $str = str_replace('"', "'", $str);
		$str = str_replace('&#187;', "'", $str);
		$str = str_replace('&#171;', "'", $str);
		$str = str_replace("&#8212;","-",$str);
		$str = str_replace("&nbsp;","",$str);
		$str = str_replace('&#39;', "'", $str);
		$str = str_replace('&quot;', "'", $str);
		$str = str_replace('&amp;', "&", $str);
		$str = str_replace('&raquo;', "", $str);
     
        return $str;
    }

    function win2utf($txt)
    {
        $in_arr = array
        (
            chr(150),                   // for Dune replace - by -
            chr(171), chr(187),        // for Dune replace «» by <>
            chr(208), chr(192), chr(193), chr(194),
            chr(195), chr(196), chr(197), chr(168),
            chr(198), chr(199), chr(200), chr(201),
            chr(202), chr(203), chr(204), chr(205),
            chr(206), chr(207), chr(209), chr(210),
            chr(211), chr(212), chr(213), chr(214),
            chr(215), chr(216), chr(217), chr(218),
            chr(219), chr(220), chr(221), chr(222),
            chr(223), chr(224), chr(225), chr(226),
            chr(227), chr(228), chr(229), chr(184),
            chr(230), chr(231), chr(232), chr(233),
            chr(234), chr(235), chr(236), chr(237),
            chr(238), chr(239), chr(240), chr(241),
            chr(242), chr(243), chr(244), chr(245),
            chr(246), chr(247), chr(248), chr(249),
            chr(250), chr(251), chr(252), chr(253),
            chr(254), chr(255)
        );   
     
        $out_arr = array
        (
            '-',
            chr(60), chr(62),          // for Dune replace «» by <>
            chr(208).chr(160), chr(208).chr(144), chr(208).chr(145),
            chr(208).chr(146), chr(208).chr(147), chr(208).chr(148),
            chr(208).chr(149), chr(208).chr(129), chr(208).chr(150),
            chr(208).chr(151), chr(208).chr(152), chr(208).chr(153),
            chr(208).chr(154), chr(208).chr(155), chr(208).chr(156),
            chr(208).chr(157), chr(208).chr(158), chr(208).chr(159),
            chr(208).chr(161), chr(208).chr(162), chr(208).chr(163),
            chr(208).chr(164), chr(208).chr(165), chr(208).chr(166),
            chr(208).chr(167), chr(208).chr(168), chr(208).chr(169),
            chr(208).chr(170), chr(208).chr(171), chr(208).chr(172),
            chr(208).chr(173), chr(208).chr(174), chr(208).chr(175),
            chr(208).chr(176), chr(208).chr(177), chr(208).chr(178),
            chr(208).chr(179), chr(208).chr(180), chr(208).chr(181),
            chr(209).chr(145), chr(208).chr(182), chr(208).chr(183),
            chr(208).chr(184), chr(208).chr(185), chr(208).chr(186),
            chr(208).chr(187), chr(208).chr(188), chr(208).chr(189),
            chr(208).chr(190), chr(208).chr(191), chr(209).chr(128),
            chr(209).chr(129), chr(209).chr(130), chr(209).chr(131),
            chr(209).chr(132), chr(209).chr(133), chr(209).chr(134),
            chr(209).chr(135), chr(209).chr(136), chr(209).chr(137),
            chr(209).chr(138), chr(209).chr(139), chr(209).chr(140),
            chr(209).chr(141), chr(209).chr(142), chr(209).chr(143)
        );   
     
        $txt = str_replace($in_arr,$out_arr,$txt);
        return $txt;
    }

    function utf2win($txt)
    {
        $out_arr = array
        (
            chr(208), chr(192), chr(193), chr(194),
            chr(195), chr(196), chr(197), chr(168),
            chr(198), chr(199), chr(200), chr(201),
            chr(202), chr(203), chr(204), chr(205),
            chr(206), chr(207), chr(209), chr(210),
            chr(211), chr(212), chr(213), chr(214),
            chr(215), chr(216), chr(217), chr(218),
            chr(219), chr(220), chr(221), chr(222),
            chr(223), chr(224), chr(225), chr(226),
            chr(227), chr(228), chr(229), chr(184),
            chr(230), chr(231), chr(232), chr(233),
            chr(234), chr(235), chr(236), chr(237),
            chr(238), chr(239), chr(240), chr(241),
            chr(242), chr(243), chr(244), chr(245),
            chr(246), chr(247), chr(248), chr(249),
            chr(250), chr(251), chr(252), chr(253),
            chr(254), chr(255)
        );   
     
        $in_arr = array
        (
            chr(208).chr(160), chr(208).chr(144), chr(208).chr(145),
            chr(208).chr(146), chr(208).chr(147), chr(208).chr(148),
            chr(208).chr(149), chr(208).chr(129), chr(208).chr(150),
            chr(208).chr(151), chr(208).chr(152), chr(208).chr(153),
            chr(208).chr(154), chr(208).chr(155), chr(208).chr(156),
            chr(208).chr(157), chr(208).chr(158), chr(208).chr(159),
            chr(208).chr(161), chr(208).chr(162), chr(208).chr(163),
            chr(208).chr(164), chr(208).chr(165), chr(208).chr(166),
            chr(208).chr(167), chr(208).chr(168), chr(208).chr(169),
            chr(208).chr(170), chr(208).chr(171), chr(208).chr(172),
            chr(208).chr(173), chr(208).chr(174), chr(208).chr(175),
            chr(208).chr(176), chr(208).chr(177), chr(208).chr(178),
            chr(208).chr(179), chr(208).chr(180), chr(208).chr(181),
            chr(209).chr(145), chr(208).chr(182), chr(208).chr(183),
            chr(208).chr(184), chr(208).chr(185), chr(208).chr(186),
            chr(208).chr(187), chr(208).chr(188), chr(208).chr(189),
            chr(208).chr(190), chr(208).chr(191), chr(209).chr(128),
            chr(209).chr(129), chr(209).chr(130), chr(209).chr(131),
            chr(209).chr(132), chr(209).chr(133), chr(209).chr(134),
            chr(209).chr(135), chr(209).chr(136), chr(209).chr(137),
            chr(209).chr(138), chr(209).chr(139), chr(209).chr(140),
            chr(209).chr(141), chr(209).chr(142), chr(209).chr(143)
        );   
     
        $txt = str_replace($in_arr,$out_arr,$txt);
        return $txt;
    }
?>