<?php

namespace RRZE\Workflow\Modules\Notifications;

defined('ABSPATH') || exit;

use Text_Diff_Renderer;

class TextDiffRendererUnified extends Text_Diff_Renderer
{
    /**
     * Number of leading context "lines" to preserve.
     */
    var $_leading_context_lines = 4;

    /**
     * Number of trailing context "lines" to preserve.
     */
    var $_trailing_context_lines = 4;

    function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
    {
        if ($xlen != 1) {
            $xbeg .= ',' . $xlen;
        }
        if ($ylen != 1) {
            $ybeg .= ',' . $ylen;
        }
        return "@@ -$xbeg +$ybeg @@";
    }

    function _context($lines)
    {
        return $this->_lines($lines, ' ');
    }

    function _added($lines)
    {
        return $this->_lines($lines, '+');
    }

    function _deleted($lines)
    {
        return $this->_lines($lines, '-');
    }

    function _changed($orig, $final)
    {
        return $this->_deleted($orig) . $this->_added($final);
    }
}
