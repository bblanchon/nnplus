<?php
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/releaseimage.php");

class AniDB
{
	const CLIENT	= 'newznab';
	const CLIENTVER = 1;

	function AniDB($echooutput=false)
	{
		$this->echooutput = $echooutput;
		$this->imgSavePath = WWW_DIR.'covers/anime/';
	}

	public function animetitlesUpdate()
	{
		$db = new DB();

		$lastUpdate = $db->queryOneRow("SELECT unixtime as utime FROM animetitles LIMIT 1");
		if(isset($lastUpdate['utime']) && (time() - $lastUpdate['utime']) < 604800)
			return;

		if ($this->echooutput)
			echo "Updating animetitles.";

		$zh = gzopen('http://anidb.net/api/animetitles.dat.gz', 'r');

		preg_match_all('/(\d+)\|\d\|.+\|(.+)/', gzread($zh, '10000000'), $animetitles);
		if(!$animetitles)
			return false;

		if ($this->echooutput)
			echo ".";

		$db->query("DELETE FROM animetitles WHERE anidbID IS NOT NULL");

		for($i = 0; $i < count($animetitles[1]); $i++) {
			$db->queryInsert(sprintf("INSERT INTO animetitles (anidbID, title, unixtime) VALUES (%d, %s, %d)",
			$animetitles[1][$i], $db->escapeString(html_entity_decode($animetitles[2][$i], ENT_QUOTES, 'UTF-8')), time()));
		}

		$db = NULL;

		gzclose($zh);

		if ($this->echooutput)
			echo " done.\n";
	}

	public function addTitle($AniDBAPIArray)
	{
		$db = new DB();

		$db->queryInsert(sprintf("INSERT INTO anidb VALUES ('', %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)",
		$AniDBAPIArray['anidbID'], $db->escapeString($AniDBAPIArray['title']), $db->escapeString($AniDBAPIArray['type']), $db->escapeString($AniDBAPIArray['startdate']),
		$db->escapeString($AniDBAPIArray['enddate']), $db->escapeString($AniDBAPIArray['related']), $db->escapeString($AniDBAPIArray['creators']),
		$db->escapeString($AniDBAPIArray['description']), $db->escapeString($AniDBAPIArray['rating']), $db->escapeString($AniDBAPIArray['picture']),
		$db->escapeString($AniDBAPIArray['categories']), $db->escapeString($AniDBAPIArray['characters']), $db->escapeString($AniDBAPIArray['epnos']),
		$db->escapeString($AniDBAPIArray['airdates']), $db->escapeString($AniDBAPIArray['episodetitles']), time()));
	}

	public function updateTitle($anidbID, $title, $type, $startdate, $enddate, $related, $creators, $description, $rating, $categories, $characters, $epnos, $airdates, $episodetitles)
	{
		$db = new DB();

		$db->query(sprintf("UPDATE anidb
		SET title=%s, type=%s, startdate=%s, enddate=%s, related=%s, creators=%s, description=%s, rating=%s, categories=%s, characters=%s, epnos=%s, airdates=%s, episodetitles=%s, unixtime=%d
		WHERE anidbID = %d", $db->escapeString($title), $db->escapeString($type), $db->escapeString($startdate), $db->escapeString($enddate), $db->escapeString($related),
		$db->escapeString($creators), $db->escapeString($description), $db->escapeString($rating), $db->escapeString($categories), $db->escapeString($characters),
		$db->escapeString($epnos), $db->escapeString($airdates), $db->escapeString($episodetitles), $anidbID, time()));
	}

	public function deleteTitle($anidbID)
	{
		$db = new DB();

		$db->query(sprintf("DELETE FROM anidb WHERE anidbID = %d", $anidbID));
	}

	public function getanidbID($title)
	{
		$db = new DB();

		$anidbID = $db->queryOneRow(sprintf("SELECT anidbID as anidbID FROM animetitles WHERE title = %s", $db->escapeString($title)));

		return $anidbID['anidbID'];
	}

	public function getAnimeList($letter='', $animetitle='')
	{
		$db = new DB();

		$rsql = '';
		if ($letter != '')
		{
			if ($letter == '0-9')
				$letter = '[0-9]';

			$rsql .= sprintf("AND anidb.title REGEXP %s", $db->escapeString('^'.$letter));
		}

		$tsql = '';
		if ($animetitle != '')
		{
			$tsql .= sprintf("AND anidb.title LIKE %s", $db->escapeString("%".$animetitle."%"));
		}

		$sql = sprintf(" SELECT anidb.ID, anidb.anidbID, anidb.title, anidb.type, anidb.categories, anidb.rating, anidb.startdate, anidb.enddate
			FROM anidb WHERE anidb.anidbID > 0 %s %s GROUP BY anidb.anidbID ORDER BY anidb.title ASC", $rsql, $tsql);

		return $db->query($sql);
	}

	public function getAnimeRange($start, $num, $animetitle='')
	{
		$db = new DB();

		if ($start === false)
			$limit = '';
		else
			$limit = " LIMIT ".$start.",".$num;

		$rsql = '';
		if ($animetitle != '')
			$rsql .= sprintf("AND anidb.title LIKE %s ", $db->escapeString("%".$animetitle."%"));

		return $db->query(sprintf(" SELECT ID, anidbID, title, description FROM anidb where 1=1 %s order by anidbID ASC".$limit, $rsql));
	}

	public function getAnimeCount($animetitle='')
	{
		$db = new DB();

		$rsql = '';
		if ($animetitle != '')
			$rsql .= sprintf("AND anidb.title LIKE %s ", $db->escapeString("%".$animetitle."%"));

		$res = $db->queryOneRow(sprintf("SELECT count(ID) AS num FROM anidb where 1=1 %s ", $rsql));

		return $res["num"];
	}

	public function getAnimeInfo($anidbID)
	{
		$db = new DB();
		$animeInfo = $db->query(sprintf("SELECT * FROM anidb WHERE anidbID = %d", $anidbID));

		return isset($animeInfo[0]) ? $animeInfo[0] : false;
	}

	public function cleanFilename($searchname)
	{
		$searchname = preg_replace('/[._~]|\s{2,}|[\s\b][-:]+[\s\b]|(\[|\()[^\]\)]+(\]|\))|(\s|\b|\-)0+/i', ' ', $searchname);
		$searchname = preg_replace('/(?=\s|\b|\-|[A-Z])0+/i', '', $searchname);
		$cleanFilename = str_ireplace('\'', '`', $searchname);

		$cleanFilename = preg_replace('/ Opening ?/i', ' OP', $cleanFilename);
		$cleanFilename = preg_replace('/ (Ending|Credits|Closing|C(?= ?\d)) ?/i', ' ED', $cleanFilename);
		$cleanFilename = preg_replace('/ (Trailer|TR(?= ?\d)) ?/i', ' T', $cleanFilename);
		$cleanFilename = preg_replace('/ (Promo|P(?= ?\d)) ?/i', ' PV', $cleanFilename);
		$cleanFilename = preg_replace('/ (Special|SP(?= ?\d)) ?(?! ?[a-z])/i', ' S', $cleanFilename);
		$cleanFilename = preg_replace('/ (OP|ED|[ST](?! ?[a-z])|PV)(v\d+)? (?!\d )/i', ' ${1}1', $cleanFilename);
		$cleanFilename = preg_replace('/ (OP|ED|[ST]|PV) (\d+)/i', ' ${1}${2}', $cleanFilename);

		$cleanFilename = preg_replace('/ (v\d|Ep(isode)? |Vol(ume)? |(The )?(Complete )?Movie(?! (\d|[ivx]))|((HD)?DVD|B(luray|[dr])(rip)?)|Rs?\d|[SD]ub(bed)?|Creditless|Picture Drama)/i', ' ', $cleanFilename);

		preg_match('/^(?P<title>.+) (?P<epno>(?:NC)?(?:[A-Z](?=\d)|[A-Z]{2,3})?(?![A-Z]|[\s\b][A-Z]|$)[\s\b]?(?:(?<![&+] |and |[\s\b]v)\d{1,3}(?!\d)(?:-\d{1,3}(?!\d))?))/i', $cleanFilename, $cleanFilename);

		$cleanFilename['title'] = (isset($cleanFilename['title'])) ? trim($cleanFilename['title']) : trim($searchname);
		$cleanFilename['epno'] = (isset($cleanFilename['epno'])) ? preg_replace('/NC|Ep|Vol|(v\d)/i', '', $cleanFilename['epno']) : 1;

		return $cleanFilename;
	}

	public function processAnimeReleases()
	{
		$db = new DB();
		$ri = new ReleaseImage();

		$results = $db->queryDirect(sprintf("SELECT searchname, ID FROM releases WHERE anidbID is NULL AND categoryID IN ( SELECT ID FROM category WHERE categoryID = %d )", Category::CAT_TV_ANIME));

		if (mysql_num_rows($results) > 0) {
			if ($this->echooutput)
				echo "Processing ".mysql_num_rows($results)." anime releases\n";

			while ($arr = mysql_fetch_assoc($results)) {

				$cleanFilename = $this->cleanFilename($arr['searchname']);
				$anidbID = $this->getanidbID($cleanFilename['title']);
				if(!$anidbID) {
					$db->query(sprintf("UPDATE releases SET anidbID = %d, rageID = %d WHERE ID = %d", -1, -2, $arr["ID"]));
					continue;
				}

				if ($this->echooutput)
					echo 'Looking up: '.$cleanFilename['title'].' -- '.$arr['searchname']."\n";

				$AniDBAPIArray = $this->getAnimeInfo($anidbID);
				$lastUpdate = ((isset($AniDBAPIArray['unixtime']) && (time() - $AniDBAPIArray['unixtime']) > 604800));

				if (!$AniDBAPIArray || $lastUpdate) {
					$AniDBAPIArray = $this->AniDBAPI($anidbID);

					if(! $lastUpdate)
						$this->addTitle($AniDBAPIArray);
					else {
						$this->updateTitle($AniDBAPIArray['anidbID'], $AniDBAPIArray['title'], $AniDBAPIArray['type'], $AniDBAPIArray['startdate'],
							$AniDBAPIArray['enddate'], $AniDBAPIArray['related'], $AniDBAPIArray['creators'], $AniDBAPIArray['description'],
							$AniDBAPIArray['rating'], $AniDBAPIArray['categories'], $AniDBAPIArray['characters'], $AniDBAPIArray['epnos'],
							$AniDBAPIArray['airdates'], $AniDBAPIArray['episodetitles']);
					}

					if($AniDBAPIArray['picture'])
						$ri->saveImage($AniDBAPIArray['anidbID'], 'http://img7.anidb.net/pics/anime/'.$AniDBAPIArray['picture'], $this->imgSavePath);
				}

				if ($AniDBAPIArray['anidbID']) {
					$epno = explode('|', $AniDBAPIArray['epnos']);
					$airdate = explode('|', $AniDBAPIArray['airdates']);
					$episodetitle = explode('|', $AniDBAPIArray['episodetitles']);

					for($i = 0; $i < count($epno); $i++) {
						if($cleanFilename['epno'] == $epno[$i]) {
							$offset = $i;
							break;
						}
						else $offset = -1; // shouldn't be necessary. unusual behaviour without it.
					}

					$airdate = isset($airdate[$offset]) ? $airdate[$offset] : $AniDBAPIArray['startdate'];
					$episodetitle = isset($episodetitle[$offset]) ? $episodetitle[$offset] : $cleanFilename['epno'];
					$tvtitle = ($episodetitle !== 'Complete Movie' && $episodetitle !== $cleanFilename['epno']) ? $cleanFilename['epno']." - ".$episodetitle : $episodetitle;

					if ($this->echooutput)
						echo '- found '.$AniDBAPIArray['anidbID']."\n";

					$db->query(sprintf("UPDATE releases SET episode=%s, tvtitle=%s, tvairdate=%s, anidbID=%d, rageID=%d WHERE ID = %d",
					$db->escapeString($cleanFilename['epno']), $db->escapeString($tvtitle), $db->escapeString($airdate), $AniDBAPIArray['anidbID'], -2, $arr["ID"]));
				}
			}

			if ($this->echooutput)
				echo "Processed ".mysql_num_rows($results)." anime releases.\n";
		}
	}

	public function AniDBAPI($anidbID)
	{
		$ch = curl_init('http://api.anidb.net:9001/httpapi?request=anime&client='.self::CLIENT.'&clientver='.self::CLIENTVER.'&protover=1&aid='.$anidbID);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

		$apiresponse = curl_exec($ch);
		if(!$apiresponse)
			return false;
		curl_close($ch);

		//TODO: SimpleXML.

		$AniDBAPIArray['anidbID'] = $anidbID;

		preg_match_all('/<title xml:lang="x-jat" type="(?:official|main)">(.+)<\/title>/i', $apiresponse, $title);
		$AniDBAPIArray['title'] = isset($title[1][0]) ? $title[1][0] : '';

		preg_match_all('/<(type|(?:start|end)date)>(.+)<\/\1>/i', $apiresponse, $type_startenddate);
		$AniDBAPIArray['type'] = isset($type_startenddate[2][0]) ? $type_startenddate[2][0] : '';
		$AniDBAPIArray['startdate'] = isset($type_startenddate[2][1]) ? $type_startenddate[2][1] : '';
		$AniDBAPIArray['enddate'] = isset($type_startenddate[2][2]) ? $type_startenddate[2][2] : '';

		preg_match_all('/<anime id="\d+" type=".+">([^<]+)<\/anime>/is', $apiresponse, $related);
		$AniDBAPIArray['related'] = isset($related[1]) ? implode($related[1], '|') : '';

		preg_match_all('/<name id="\d+" type=".+">([^<]+)<\/name>/is', $apiresponse, $creators);
		$AniDBAPIArray['creators'] = isset($creators[1]) ? implode($creators[1], '|') : '';

		preg_match('/<description>([^<]+)<\/description>/is', $apiresponse, $description);
		$AniDBAPIArray['description'] = isset($description[1]) ? $description[1] : '';

		preg_match('/<permanent count="\d+">(.+)<\/permanent>/i', $apiresponse, $rating);
		$AniDBAPIArray['rating'] = isset($rating[1]) ? $rating[1] : '';

		preg_match('/<picture>(.+)<\/picture>/i', $apiresponse, $picture);
		$AniDBAPIArray['picture'] = isset($picture[1]) ? $picture[1] : '';

		preg_match_all('/<category id="\d+" parentid="\d+" hentai="(?:true|false)" weight="\d+">\s+<name>([^<]+)<\/name>/is', $apiresponse, $categories);
		$AniDBAPIArray['categories'] = isset($categories[1]) ? implode($categories[1], '|') : '';

		preg_match_all('/<character id="\d+" type=".+" update="\d{4}-\d{2}-\d{2}">\s+<name>([^<]+)<\/name>/is', $apiresponse, $characters);
		$AniDBAPIArray['characters'] = isset($characters[1]) ? implode($characters[1], '|') : '';

		preg_match('/<episodes>\s+<episode.+<\/episodes>/is', $apiresponse, $episodes);
		preg_match_all('/<epno>(.+)<\/epno>/i', $episodes[0], $epnos);
		$AniDBAPIArray['epnos'] = isset($epnos[1]) ? implode($epnos[1], '|') : '';
		preg_match_all('/<airdate>(.+)<\/airdate>/i', $episodes[0], $airdates);
		$AniDBAPIArray['airdates'] = isset($airdates[1]) ? implode($airdates[1], '|') : '';
		preg_match_all('/<title xml:lang="en">(.+)<\/title>/i', $episodes[0], $episodetitles);
		$AniDBAPIArray['episodetitles'] = isset($episodetitles[1]) ? implode($episodetitles[1], '|') : '';

		sleep(2); //to comply with flooding rule.

		return $AniDBAPIArray;
	}
}

?>
