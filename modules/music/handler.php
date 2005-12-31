<?php
/**
 * MythMusic browser
 *
 * @url         $URL: svn+ssh://xris@cvs.mythtv.org/var/lib/svn/trunk/mythplugins/mythweb/modules/music/init.php $
 * @date        $Date$
 * @version     $Revision$
 * @author      $Author$
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Music
 *
/**/

// Make sure the music directory exists
    if (file_exists('data/music')) {
    // File is not a directory or a symlink
        if (!is_dir('data/music') && !is_link('data/music')) {
            $Error = 'An invalid file exists at data/music.  Please remove it in'
                    .' order to use the music portions of MythWeb.';
            require_once 'templates/_error.php';
        }
    }
// Create the symlink, if possible.
//
// NOTE:  Errors have been disabled because if I turn them on, people hosting
//        MythWeb on Windows machines will have issues.  I will turn the errors
//        back on when I find a clean way to do so.
//
    else {
        $dir = $db->query_col('SELECT data
                                 FROM settings
                                WHERE value="MusicLocation" AND hostname=?',
                              hostname
                             );
        if ($dir) {
            $ret = @symlink($dir, 'data/music');
            if (!$ret) {
                #$Error = "Could not create a symlink to $dir, the local MythMusic directory"
                #        .' for this hostname ('.hostname.').  Please create a symlink to your'
                #        .' MythMusic directory at data/music in order to use the music'
                #        .' portions of MythWeb.';
                #require_once 'templates/_error.php';
            }
        }
        else {
            #$Error = 'Could not find a value in the database for the MythMusic directory'
            #        .' for this hostname ('.hostname.').  Please create a symlink to your'
            #        .' MythMusic directory at data/music in order to use the music'
            #        .' portions of MythWeb.';
            #require_once 'templates/_error.php';
        }
    }

//
//  Someday, music.php will let us stream
//  entire playlists to any spot on planet earth
//
require_once theme_dir.'music/music.php';

$mythmusic = new mythMusic();
$mythmusic->display();

class mythMusic {
    var $filterPlaylist;
    var $filterArtist;
    var $filterAlbum;
    var $filterGenre;
    var $filterRank;
    var $filterSonglist;
    var $keepFilters;
    var $filter;
    var $totalCount;
    var $offset;

    var $result;


    var $intid;
    var $artist;
    var $album;
    var $title;
    var $genre;
    var $length;
    var $rating;
    var $filename;
    var $urlfilename;

    var $alphalink;
    var $alphaoffset;

    function mythMusic()
    {
        if($_GET['offset'] >=0 )
            $this->offset=$_GET['offset'];
        else
            $this->offset=0;

        /**** If alphalink set, then change offset to new value ****/
        if ($_GET['alphalink']) {
            $alphalink = $_GET['alphalink'];
            $result=mysql_query("select count(1) from musicmetadata where upper(artist) < ".escape($alphalink));
            $alphaoffset=mysql_fetch_row($result);
            $this->offset=$alphaoffset[0];
            mysql_free_result($result);
        }

        if($_GET['filterPlaylist'])
        {
            $this->filterPlaylist=$_GET['filterPlaylist'];
            $_GET['filterPlaylist'];
        }
        else
            $this->filterPlaylist="_All_";

        if($_GET['filterArtist'])
        {
            $this->filterArtist=$_GET['filterArtist'];
        }
        else
            $this->filterArtist="_All_";

        if($_GET['filterAlbum'])
        {
            $this->filterAlbum=$_GET['filterAlbum'];
        }
        else
            $this->filterAlbum="_All_";
        if($_GET['filterGenre'])
        {
            $this->filterGenre=$_GET['filterGenre'];
        }
        else
            $this->filterGenre="_All_";


        if($_GET['filterRank'])
            $this->filterRank=$_GET['filterRank'];
        else
            $this->filterRank="_All_";
    }

    function readRow()
    {

            if($row=mysql_fetch_row($this->result))
            {
                $this->intid=$row[0];
                $this->artist=$row[1];
                $this->album=$row[2];
                $this->title=$row[3];
                $this->genre=$row[4];
                $this->length=$row[5];
                $this->rating=$row[6];
                $this->filename=$row[7];

                $this->urlfilename=root.'data/music';
                global $musicdir;
                foreach (preg_split('/\//', substr($this->filename, strlen($musicdir))) as $dir) {
                    if (!$dir) continue;
                    $this->urlfilename .= '/'.rawurlencode(utf8tolocal($dir));
                }

                return(true);
            }
            return(false);
    }



    function display()
    {
        $music = new Theme_music();
        $this->init($music->getMaxPerPage());
        $music->setOffset($this->offset);
        $music->setTotalCount($this->totalCount);

        $music->print_header($this->filterPlaylist,$this->filterArtist,$this->filterAlbum,$this->filterGenre);
        if($this->totalCount > 0)
        {
            while($this->readRow())
            {
                $music->printDetail($this->title,$this->length,$this->artist,$this->album,$this->genre,$this->urlfilename);
            }
        }
        else
        {
            $music->printNoDetail();
        }
        if($this->result)
            mysql_free_result($this->result);

        $music->print_footer();
    }

    function prepFilter()
    {
        $prevFilter=0;
        $thisFilter="";

        if($this->filterPlaylist != "_All_")
        {
            $playlistResult = mysql_query("select playlistid,name,songlist,hostname from musicplaylist where playlistid=".escape($this->filterPlaylist));
            if($playlistResult)
            {
                if(mysql_num_rows($playlistResult)==1)
                {
                    $row=mysql_fetch_row($playlistResult);
                    if($row)
                    {

                        $this->filterSonglist=$row[2];
                        if($prevFilter==1)
                            $this->filter=$this->filter . "and intid in (" . $this->filterSonglist . ")";
                        else
                        {
                            $this->filter="intid in (" . $this->filterSonglist . ")";
                            $prevFilter=1;
                        }

                        $this->keepFilters="&amp;filterPlaylist=" . urlencode($this->filterPlaylist);

                    }
                }
            }
        }

        if($this->filterArtist != "_All_" )
        {
            if($prevFilter==1)
                $this->filter=$this->filter . "and artist=".escape($this->filterArtist);
            else
            {
                $this->filter="artist=".escape($this->filterArtist);
                $prevFilter=1;
            }

            $this->keepFilters="&amp;filterArtist=" . urlencode($this->filterArtist);

        }
        if($this->filterAlbum != "_All_")
        {
            if($prevFilter==1)
            {
                $this->filter= $this->filter . "and album=\"" . $this->filterAlbum . "\"";
            }
            else
            {
                $this->filter="album=\"" . $this->filterAlbum . "\"";
                $prevFilter=1;
            }
            $this->keepFilters =$this->keepFilters . "&amp;filterAlbum=" . urlencode($this->filterAlbum) ;

        }
        if($this->filterGenre != "_All_")
        {
            if($prevFilter==1)
            {
                $this->filter= $this->filter . "and genre=" . $this->filterGenre ;
            }
            else
            {
                $this->filter="genre=\"" . $this->filterGenre . "\"";
                $prevFilter=1;
            }
            $this->keepFilters =$this->keepFilters . "&amp;filterGenre=" . urlencode($this->filterGenre);

        }

        if($this->filterRank != "_All_")
        {
            if($prevFilter==1)
            {
                $this->filter=$this->filter . "and rank=" . $this->filterRank;
            }
            else
            {
                $this->filter="rank=" . $this->filterRank;
                $prevFilter=1;
            }
            $this->keepFilters =$this->keepFilters . "&amp;filterRank=" . urlencode($this->filterRank);
        }



    }

    function init($maxPerPage) {
        global $db;
        $this->prepFilter();
        if (empty($this->filter))
            $this->totalCount = $db->query_col('SELECT COUNT(*) FROM musicmetadata');
        else
            $this->totalCount = $db->query_col('SELECT COUNT(*) FROM musicmetadata WHERE '.$this->filter);

        if ($this->totalCount > 0) {
            if($this->offset > 0) {
                $limitText='LIMIT ' . $this->offset . ',' . $maxPerPage;
            }
            else
                $limitText='LIMIT ' . $maxPerPage;

            if (empty($this->filter))
                $this->result=mysql_query("select intid,artist,album,title,genre,length,rating,filename from musicmetadata order by artist,album,tracknum " . $limitText);
            else
                $this->result=mysql_query("select intid,artist,album,title,genre,length,rating,filename from musicmetadata where $this->filter order by artist,album,tracknum $limitText");
        }
    }
}

