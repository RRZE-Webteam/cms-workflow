jQuery(document).ready(function($) {
    
    function task_list_content_loading() {
        $("#task-list-content").css("background-image", "url("+decodeURIComponent(task_list_vars.ajax_loader)+")");
        $("#task-list-content").css("background-position", "right top");
        $("#task-list-content").css("background-repeat", "no-repeat");
        $("#task-list-content-error").empty();
        $("#task-list-content-error").css("display", "none");
    }
    
    function task_list_content_loading_clear() {
        $("#task-list-content").css("background-image", "");
        $("#task-list-content").css("background-position", "");
        $("#task-list-content").css("background-repeat", "");
    }
    
    function task_list_reload_current_page() {
        var data = {
            action:     "task_list_ajax_get_page",
            post_id:    task_list_vars.post_id,
            nonce:      task_list_vars.nonce_task_list_ajax_get_page
        };
        
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            if ( response.error ) {
                $("#task-list-content-error").html(response.error);
                $("#task-list-content-error").css("display", "block");
            } else {
                $("#task-list-content").html(response);
            }
            
            task_list_content_loading_clear();
            
        });
    }
    
    $("#task-list-content").delegate(".task-item-link", "click", function(e) {
        
        e.preventDefault();
        
        if($(this).closest(".task-item").children(".task-item-content").is(":visible")) {
            $(this).closest(".task-item").children(".task-item-content").slideUp();
            $(this).closest(".task-item").css("background-color", "transparent");
        } else {
            $(this).closest(".task-item").children(".task-item-content").slideDown();
            $(this).closest(".task-item").css("background-color", "#FFFBCC");
        }
        
    });
    
    $("#task-list-content").delegate(".mark-as-done", "change", function() {
        
        task_list_content_loading();
        
        var clicked_task    = $(this);
        var task_id         = clicked_task.val();
        var data            = {
            action:     "task_list_ajax_mark_as_done",
            task_id:    task_id,
            checked:    clicked_task.attr("checked"),
            marker:     task_list_vars.current_user_id,
            post_id:    task_list_vars.post_id,
            nonce:      task_list_vars.nonce_task_list_ajax_mark_as_done
        };
            
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            
            if(response.length > 0) {
                $("#task-list-content-error").html(response);
                $("#task-list-content-error").css("display", "block");
            } else {
                
                if(clicked_task.attr("checked") == "checked") {
                    clicked_task.closest(".task-item-content").children(".task-content-body").append('<p class="marked-as-done">' + sprintf(task_list_vars.message_2, task_list_vars.current_user_display_name, task_list_vars.current_date) + '</p>');
                    clicked_task.closest(".task-item").find(".task-item-link").css("text-decoration", "line-through");
                } else {
                    clicked_task.closest(".task-item-content").find(".marked-as-done").remove();
                    clicked_task.closest(".task-item").find(".task-item-link").css("text-decoration", "none");
                }
                
            }
            
            task_list_content_loading_clear();
            
        });
    });
    
    $("#task-list-content").delegate(".task-self-assignment", "click", function(e) {
        e.preventDefault();
        
        task_list_content_loading();
        
        var clicked_task    = $(this);
        var data            = {
            action:     "task_list_ajax_self_assignment",
            task_id:    clicked_task.attr("rel"),
            post_id:    task_list_vars.post_id,
            nonce:      task_list_vars.nonce_task_list_ajax_self_assignment
        };
            
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            
            if(response.length > 0) {
                $("#task-list-content-error").html(response);
                $("#task-list-content-error").css("display", "block");
            } else {
                task_list_reload_current_page();
            }
        
            task_list_content_loading_clear();
        
        });
    });
    
    $("#task-list-content").delegate(".task-self-unassignment", "click", function(e) {
        e.preventDefault();
        
        task_list_content_loading();
        
        var clicked_task    = $(this);
        var data            = {
            action:     "task_list_ajax_self_unassignment",
            task_id:    clicked_task.attr("rel"),
            post_id:    task_list_vars.post_id,
            nonce:      task_list_vars.nonce_task_list_ajax_self_unassignment
        };
            
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            
            if(response.length > 0) {
                $("#task-list-content-error").html(response);
                $("#task-list-content-error").css("display", "block");
            } else {
                task_list_reload_current_page();
            }
        
            task_list_content_loading_clear();
        
        });
    });
    
    $("#task-list-content").delegate(".task-delete", "click", function(e) {
        e.preventDefault();
        
        var agree = confirm(task_list_vars.message_1);
        if (! agree)
        	return false;
        
        task_list_content_loading();
        
        var clicked_task    = $(this);
        var data            = {
            action:     "task_list_ajax_delete_task",
            task_id:    clicked_task.attr("rel"),
            post_id:    task_list_vars.post_id,
            nonce:      task_list_vars.nonce_task_list_ajax_delete_task
        };
            
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            
            if(response.length > 0) {
                $("#task-list-content-error").html(response);
                $("#task-list-content-error").css("display", "block");
            } else {
                clicked_task.closest(".task-item").fadeOut();
                task_list_reload_current_page();
            }
        
            task_list_content_loading_clear();
        
        });
    });
        
    $("#task-list-new").click(function (e) {
        if($("#task-list-new-content").is(":visible")) {
            $("#task-list-new-content").slideUp();
        } else {
            $("#task-list-new-content").slideDown();
        }
        e.preventDefault();
    });
    
    $("#new-task-title").bind("input propertychange", function () {
        $("#new-task-submit").css("opacity", "1");
        $("#new-task-submit").removeAttr("disabled");
    });
    
    $("#new-task-description").focusin(function() {
        $("#new-task-description").attr("rows", "5");
    });
    
    $("#new-task-submit").click(function (e) {
        e.preventDefault();
        
        $("#new-task-loading").css("display", "inline");
        $("#new-task-error").css("display", "none");
        
        var task_author = 0;
        if($("#task-list-author option:selected").val() > 0) {
            task_author = $("#task-list-author option:selected").val();
        }
        var data = {
            action:         "task_list_ajax_new_task_submit",
            task_title:     $("#new-task-title").val(),
            task_adder:     task_list_vars.current_user_id,
            task_description:     $("#new-task-description").val(),
            task_author:    task_author,
            task_priority:  $("#task-priority").val(), 
            post_id:        task_list_vars.post_id,
            nonce:          task_list_vars.nonce_task_list_ajax_new_task
        };
        
        $.post(decodeURIComponent(task_list_vars.ajax_url), data, function(response) {
            
            if ( response.error ) {
                $("#new-task-error").css("display", "block");
                $("#new-task-error").html(response.error);
            } else {
                $("#task-list-no-tasks-available").remove();
                
                var new_task_data = {
                    action:         "task_list_ajax_print_after_new_task",
                    task_title:     $("#new-task-title").val(),
                    task_adder:     task_list_vars.current_user_id,
                    task_description:     $("#new-task-description").val(),
                    task_author:    $("#task-list-author option:selected").val(),
                    task_priority:  $("#task-priority").val(),
                    post_id:        task_list_vars.post_id,
                    nonce:          task_list_vars.nonce_task_list_ajax_print_after_new_task
                };
                
                $.post(decodeURIComponent(task_list_vars.ajax_url), new_task_data, function(response) {
                    
                    if ( response.error ) {
                        $("#new-task-error").css("display", "block");
                        $("#new-task-error").html(response.error);
                    } else {
                        $("#task-list-content").append(response);                
                        $("#task-list-new-content").slideUp();
                        $("#new-task-form").find("input[type=text]").val("");
                        $("#new-task-form").find("textarea").val("");                        
                    }
                        
                });
                
            }
            
            $("#new-task-loading").css("display", "none");
            
        });
        
    });
    
});