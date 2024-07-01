(function($) {
	$(document).ready( function() {
		$("#site-activity-post-type-select").multipleSelect({
            width: '95%',
            placeholder: workflow_dashboard_vars.placeholder,
            selectAllText: workflow_dashboard_vars.selectAllText,
            allSelected: workflow_dashboard_vars.allSelected,
            countSelected: workflow_dashboard_vars.countSelected,
            onOpen: function() {
                $("div.ms-drop").removeClass("hiding_dropdown").addClass("showing_dropdown");
            },
            onClose: function() {
                $("div.ms-drop").removeClass("showing_dropdown").toggleClass("hiding_dropdown");
            }
        });        
		$("#recent-drafts-widget-post-type-select").multipleSelect({
            width: '95%',
            placeholder: workflow_dashboard_vars.placeholder,
            selectAllText: workflow_dashboard_vars.selectAllText,
            allSelected: workflow_dashboard_vars.allSelected,
            countSelected: workflow_dashboard_vars.countSelected,
            onOpen: function() {
                $("div.ms-drop").removeClass("hiding_dropdown").addClass("showing_dropdown");
            },
            onClose: function() {
                $("div.ms-drop").removeClass("showing_dropdown").toggleClass("hiding_dropdown");
            }
        });              
		$("#recent-pending-widget-post-type-select").multipleSelect({
            width: '95%',
            width: '95%',
            placeholder: workflow_dashboard_vars.placeholder,
            selectAllText: workflow_dashboard_vars.selectAllText,
            allSelected: workflow_dashboard_vars.allSelected,
            countSelected: workflow_dashboard_vars.countSelected,            
            onOpen: function() {
                $("div.ms-drop").removeClass("hiding_dropdown").addClass("showing_dropdown");
            },
            onClose: function() {
                $("div.ms-drop").removeClass("showing_dropdown").toggleClass("hiding_dropdown");
            }
        });        
	});
})(jQuery);
