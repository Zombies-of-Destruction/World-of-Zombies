<?php
    $sql_query = "SELECT aktion, aktion_id, aktion_start, aktion_ende FROM `char` WHERE `userID` = '" . $_SESSION['userID'] . "'";
    $result = mysql_query($sql_query);
    $aktion = mysql_fetch_assoc($result);

    echo '<h1>'.text_ausgabe("unterwegs", 0, $bg['sprache']).'</h1>';
    echo text_ausgabe("unterwegs", 1, $bg['sprache']).'<br><br>';
?>
<table width="100%">
    <tr>
        <td>
            <div>
<?php
	if ($dsatz["aktion_ende"]-time()<=0) {
        switch ($aktion['aktion']) {
            case 'ABBAUEN':
                echo '<h2>'.text_ausgabe("abbauen", 2, $bg['sprache']).' ';
                $sql['gebiet']="SELECT * FROM abbau_gebiet WHERE ID='".$aktion['aktion_id']."'";
                $query['gebiet']=mysql_query($sql['gebiet']);
                while ($row['gebiet']=mysql_fetch_assoc($query['gebiet'])) {
                    $gebiet=$row['gebiet'];
                }
                echo text_ausgabe("gebiet", $gebiet['ID'], $bg['sprache']).'</h2>';
				$sql['gebiet']="SELECT * FROM abbau_gebiet WHERE gebiet='".$gebiet['gebiet']."'";
                $query['gebiet']=mysql_query($sql['gebiet']);
				while ($row['gebiet']=mysql_fetch_assoc($query['gebiet'])) {
					$gebiet=$row['gebiet'];
					$faktor=player_abbau_faktor($_SESSION['userID'], 1);
					$abbau=$gebiet['grundwert']*$faktor;
					if (inventory_add($_SESSION['userID'], $gebiet['itemID'], $abbau)) {
						echo $abbau.' '.text_ausgabe("item", $gebiet['itemID'], $bg['sprache']).' '.text_ausgabe("abbauen", 3, $bg['sprache']).'<br>';
					}
				}
				$sql_char_level="SELECT `level` FROM `char` WHERE userID='".$_SESSION["userID"]."'";
				$query_char_level=mysql_query($sql_char_level);
				$level=mysql_result($query_char_level,0,0)-1;
				$sql_mob="SELECT mob_exp FROM mob_db WHERE mob_level='".$level."' ORDER BY RAND( )";
				$query_mob=mysql_query($sql_mob);
				if (mysql_num_rows($query_mob)>0) {
					$exp=mysql_result($query_mob,0,0);
				} else {
					$sql_mob="SELECT mob_exp FROM mob_db WHERE mob_level<'".$level."' ORDER BY mob_exp DESC LIMIT 0,1";
					$query_mob=mysql_query($sql_mob);
					if (mysql_num_rows($query_mob)>0) {
						$exp=mysql_result($query_mob,0,0);
					}
				}
				
                $sql['user_aktion']="UPDATE `char`
                                    SET aktion='',
										exp=exp+".$exp.",
										wasser=wasser-5,
                                        aktion_id=0,
                                        aktion_start=0,
                                        aktion_ende=0,
										Items_Abbau=Items_Abbau+".$abbau."
                                    WHERE userID=".$_SESSION["userID"];
                mysql_query($sql['user_aktion']);
                break;
			case 'MÜLL':
				echo '<h2>'.text_ausgabe("muell_sammeln", 0, $bg['sprache']).'</h2>';
				// MÜLL AUSWÄHLEN
				if ($aktion['aktion_id']>0) {
					$sql['muell']="SELECT * FROM abfall WHERE spieler=".$aktion['aktion_id']." ORDER BY RAND () LIMIT 0,1";
				} else {
					$sql['muell']="SELECT * FROM abfall ORDER BY RAND () LIMIT 0,1";
				}
				$query['muell']=mysql_query($sql['muell']);
				while ($row['muell']=mysql_fetch_assoc($query['muell'])) {
					//inventory_add($user, $item, $menge)
					if (inventory_add($_SESSION["userID"], $row['muell']['item'], $row['muell']['menge'])) {
						echo text_ausgabe("muell_ok", 0, $bg['sprache']).'<br>';
						echo text_ausgabe("item_erhalten", 0, $bg['sprache']).'<br><br>';
						echo $row['muell']['menge'].' x '.text_ausgabe("item", $row['muell']['item'], $bg['sprache']).'<br>';
						$tmp_sql=array_set_mysqlstring($row['muell']);
						$tmp_sql=str_replace(", ", " AND ", $tmp_sql);
						$sql['muell_done']="DELETE FROM abfall WHERE ".$tmp_sql;
						mysql_query($sql['muell_done']);
					} else {
						echo text_ausgabe("muell_fail", 0, $bg['sprache']).'<br>';
					}
				}
				// AKTION WIEDER BEENDEN
				$sql['user_aktion']="UPDATE `char`
                                    SET aktion='',
										wasser=wasser-5,
                                        aktion_id=0,
                                        aktion_start=0,
                                        aktion_ende=0
                                    WHERE userID=".$_SESSION["userID"];
                mysql_query($sql['user_aktion']);
				break;
            case 'KAMPF_MOB':
                echo '<h2>'.text_ausgabe("kampf", 0, $bg['sprache']).'</h2>';
                $sql_monster="SELECT * FROM mob_db WHERE mob_id='".$aktion['aktion_id']."'";
				$query_monster=mysql_query($sql_monster);
				while ($row_monster=mysql_fetch_assoc($query_monster)) {
					$monster=$row_monster;
				}
//Kampfsystem
				$char=get_player_status($_SESSION['userID']);
				/*
				echo '<pre>';
				var_dump($monster);
				var_dump($char);			
				echo '</pre>';
				*/
				// KAMPF
				$runde=0;
				while ($monster['mob_leben']>0 AND $char['nahrung']>0) {
				    $runde++;
				    echo '<b>Runde '.$runde.':</b><br>';
				    //WAFFE UND MUNITONSBERECHNUNG - Später
			            $waffenart=1;
				    $sql_waffenschaden="SELECT mindmg, maxdmg FROM `item_db` WHERE itemID=".$char['waffen'][$waffenart];
//				    echo $sql_waffenschaden;
				    $item_name=text_ausgabe("item", $char['waffen'][$waffenart], $bg['sprache']);
				    $query_waffenschaden=mysql_query($sql_waffenschaden);
				    if (mysql_num_rows($query_waffenschaden)>0) {
				        $char['min_schaden']=@mysql_result($query_waffenschaden,0,0);
				        $char['max_schaden']=@mysql_result($query_waffenschaden,0,1);
				    }
				    echo 'Du benutzt: '.$item_name.'<br>';
				    if($waffenart==1) {
   				        $mob_schaden=rand($monster['min_schaden'],$monster['max_schaden']);
				        $mob_schaden=$mob_schaden*(1+($char['ruestung']/100));
					$mob_schaden=(int)$mob_schaden;
					$char['nahrung']=$char['nahrung']-$mob_schaden;
					$char_schaden=rand($char['min_schaden'], $char['max_schaden']);
					$char_schaden=(int)$char_schaden;
					$monster['mob_leben']=$monster['mob_leben']-$char_schaden;
					//$char['gesundheit']=(int)$char['gesundheit'];
					//$monster['mob_lebel']=(int)$monster['mob_lebel'];
					echo "Das Monster trifft dich mit ".$mob_schaden." Schadenspunkte! (Noch ".$char['nahrung'].")";
					echo "Du triffst das Monster mit ".$char_schaden." Schadenspunkte! (Noch ".$monster['mob_leben'].")";
					echo '<br>';
					echo '<br>';
			            }
				}
				if ($char['nahrung']>0) {
				   //Spieler verliert
				   echo "Du hast das Monster besiegt!";
				   // EXP und so hinzufügen!	
				   //var_dump($monster); 
				   $sql_char_update="UPDATE `char` SET exp=exp+".$monster['mob_exp']." WHERE userID=".$_SESSION["userID"];
				   mysql_query($sql_char_update);
				   echo '<br>';
				   echo 'Du hast '.$monster['mob_exp'].' EXP gewonnen.<br>';
				   if (inventory_add($_SESSION['userID'], 20000, 1)) {
				     echo '1 x '.text_ausgabe("item", 20000, $bg['sprache']).' erhalten.<br>';
				   } else {
				     echo 'Leider nicht genug Platz um 1 x '.text_ausgabe("item", 20000, $bg['sprache']).' zu erhalten.<br>';
				   }
				} else {
				   //Spieler gewinnt
				   echo "Verloren, Looser!";
				   $char['nahrung']=1;
				}    
				
				$sql['user_aktion']="UPDATE `char`
                                    SET aktion='',
										wasser=wasser-5,
										nahrung=".$char['nahrung'].",
										Monster_Wins=Monster_Wins+1,
                                        aktion_id=0,
                                        aktion_start=0,
                                        aktion_ende=0
                                    WHERE userID=".$_SESSION["userID"];
                mysql_query($sql['user_aktion']);
                break;
        }
    } else {
        switch ($aktion['aktion']) {
            case 'ABBAUEN':
                echo '<h2>'.text_ausgabe("abbauen", 2, $bg['sprache']).' ';
                $sql['gebiet']="SELECT * FROM abbau_gebiet WHERE ID='".$aktion['aktion_id']."'";
                $query['gebiet']=mysql_query($sql['gebiet']);
                while ($row['gebiet']=mysql_fetch_assoc($query['gebiet'])) {
                    $gebiet=$row['gebiet'];
                }
                echo text_ausgabe("gebiet", $gebiet['ID'], $bg['sprache']).'</h2>';
                echo text_ausgabe("abbauen", 0, $bg['sprache']).'<br>';
                $sql['abbaugebiet']="SELECT * FROM abbau_gebiet WHERE gebiet='".$gebiet['gebiet']."'";
                $query['abbaugebiet']=mysql_query($sql['abbaugebiet']);
                while ($row['abbaugebiet']=mysql_fetch_assoc($query['abbaugebiet'])) {
                    echo item_bilder($row['abbaugebiet']['itemID'], $art="show");
                    echo '&nbsp;'.text_ausgabe("item", $row['abbaugebiet']['itemID'], $bg['sprache']).'<br>';
                }
                break;
			case 'MÜLL':
                echo '<h2>'.text_ausgabe("sammel_müll", 0, $bg['sprache']).'</h2>';
                echo text_ausgabe("sammel_müll_text", 0, $bg['sprache']).'<br>';
                break;
            case 'KAMPF_MOB':
                echo '<h2>'.text_ausgabe("kampf", 0, $bg['sprache']).'</h2>';
                echo text_ausgabe("kampf_text", 0, $bg['sprache']).'<br>';
                break;
        }
?>
        <div id="zeitanzeige"></div>
        <script>
            function zeitanzeige() {
                var $jq = jQuery.noConflict();
                $jq("#zeitanzeige").load("zeitanzeige.php?start=<?php echo $aktion["aktion_start"]; ?>&ende=<?php echo $aktion["aktion_ende"]; ?>&till=<?php echo time(); ?>");
                window.setTimeout('zeitanzeige()',500);
            }
            zeitanzeige();
        </script>
        <?php
        // Abbrechen
            echo '<a href="site/aktion_abbruch.php">Abbrechen</a><br>';
        // Beschleunigen
            echo '<a href="site/aktion_speed.php">Beschleunigen</a><br>';

    }
?>
            </div>
        </td>
    </tr>
</table>
