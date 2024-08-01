<?php

namespace RRZE\Workflow\Modules\Notifications;

defined('ABSPATH') || exit;

use WP_Text_Diff_Renderer_Table;

class TextDiffRendererTable extends WP_Text_Diff_Renderer_Table
{
    var $_leading_context_lines  = 2;
    var $_trailing_context_lines = 2;
}
