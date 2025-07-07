/**
 * WooCommerce Data Cleanup - Admin JS
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    // Initialize Select2 for user selection
    $("#wc-data-cleanup-user-select").select2({
      ajax: {
        url: wc_data_cleanup_params.ajax_url,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return {
            search: params.term,
            page: params.page || 1,
            action: "wc_data_cleanup_get_users",
            nonce: wc_data_cleanup_params.nonce,
            include_data: true, // Always include data for indicators
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;

          // Add pagination information
          var pagination = {
            more: data.pagination && data.pagination.more ? true : false,
          };

          return {
            results: data.results,
            pagination: pagination,
          };
        },
        cache: false,
      },
      minimumInputLength: 0, // Allow searches with any length of input
      placeholder: $(this).data("placeholder"),
      templateResult: formatUserResult,
      templateSelection: formatUserSelection,
      language: {
        inputTooShort: function () {
          return "Start typing to search or press Enter to see all";
        },
        searching: function () {
          return "Searching...";
        },
        noResults: function () {
          return "No users found. Try a different search term.";
        },
      },
    });

    // Initialize Select2 for customer selection
    $("#wc-data-cleanup-customer-select").select2({
      ajax: {
        url: wc_data_cleanup_params.ajax_url,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return {
            search: params.term,
            page: params.page || 1,
            action: "wc_data_cleanup_get_customers",
            nonce: wc_data_cleanup_params.nonce,
            include_data: true, // Always include data for indicators
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;

          // Add pagination information
          var pagination = {
            more: data.pagination && data.pagination.more ? true : false,
          };

          return {
            results: data.results,
            pagination: pagination,
          };
        },
        cache: false,
      },
      minimumInputLength: 0, // Allow searches with any length of input
      placeholder: $(this).data("placeholder"),
      templateResult: formatCustomerResult,
      language: {
        inputTooShort: function () {
          return "Start typing to search or press Enter to see all";
        },
        searching: function () {
          return "Searching...";
        },
        noResults: function () {
          return "No customers found. Try a different search term.";
        },
      },
    });

    // Initialize Select2 for order selection
    $("#wc-data-cleanup-order-select").select2({
      ajax: {
        url: wc_data_cleanup_params.ajax_url,
        dataType: "json",
        delay: 250,
        data: function (params) {
          return {
            search: params.term,
            page: params.page || 1,
            action: "wc_data_cleanup_get_orders",
            nonce: wc_data_cleanup_params.nonce,
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;

          // Add pagination information
          var pagination = {
            more: data.pagination && data.pagination.more ? true : false,
          };

          return {
            results: data.results,
            pagination: pagination,
          };
        },
        cache: false,
      },
      minimumInputLength: 0, // Allow searches with any length of input
      placeholder: $(this).data("placeholder"),
      templateResult: formatOrderResult,
      language: {
        inputTooShort: function () {
          return "Start typing to search or press Enter to see all";
        },
        searching: function () {
          return "Searching...";
        },
        noResults: function () {
          return "No orders found. Try a different search term.";
        },
      },
    });

    // Force load indicators for user and customer data on initial selection
    $("#wc-data-cleanup-user-select").on("select2:select", function (e) {
      var userData = e.params.data;
      if (typeof userData.has_orders === "undefined") {
        // Load data for this user
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_users",
            search: userData.id,
            nonce: wc_data_cleanup_params.nonce,
            include_data: true,
          },
          success: function (data) {
            if (data && data.results && data.results.length > 0) {
              var loadedUserData = data.results[0];

              // Update the option in the select element
              var option = $("#wc-data-cleanup-user-select").find(
                'option[value="' + userData.id + '"]'
              );
              option.data("has_orders", loadedUserData.has_orders);
              option.data("has_posts", loadedUserData.has_posts);
              option.data("has_comments", loadedUserData.has_comments);

              // Trigger a change to refresh the display
              $("#wc-data-cleanup-user-select").trigger("change");
            }
          },
        });
      }
    });

    $("#wc-data-cleanup-customer-select").on("select2:select", function (e) {
      var customerData = e.params.data;
      if (typeof customerData.has_orders === "undefined") {
        // Load data for this customer
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_customers",
            search: customerData.id,
            nonce: wc_data_cleanup_params.nonce,
            include_data: true,
          },
          success: function (data) {
            if (data && data.results && data.results.length > 0) {
              var loadedCustomerData = data.results[0];

              // Update the option in the select element
              var option = $("#wc-data-cleanup-customer-select").find(
                'option[value="' + customerData.id + '"]'
              );
              option.data("has_orders", loadedCustomerData.has_orders);
              option.data("order_count", loadedCustomerData.order_count);

              // Trigger a change to refresh the display
              $("#wc-data-cleanup-customer-select").trigger("change");
            }
          },
        });
      }
    });

    // Replace the order status dropdown with checkboxes
    function convertOrderStatusToCheckboxes() {
      var $statusDropdown = $("#wc-data-cleanup-order-status");
      var $statusContainer = $("<div>").addClass(
        "wc-data-cleanup-status-checkboxes"
      );
      var $statusDescription = $("<p>")
        .addClass("description")
        .text(
          "Select one or more statuses to delete. Preview orders matching these statuses will appear below."
        );
      var $statusCheckboxContainer = $("<div>").addClass(
        "status-checkbox-container"
      );
      var $statusLoadingMsg = $("<div>")
        .addClass("status-loading-message")
        .text("Loading order statuses...");
      var $previewContainer = $("<div>")
        .addClass("wc-data-cleanup-status-preview")
        .hide();
      var $previewHeading = $("<h5>").text(
        "Orders matching selected statuses:"
      );
      var $previewList = $("<ul>").addClass("order-preview-list");
      var $previewLoadingMsg = $("<div>")
        .addClass("preview-loading-message")
        .text("Loading matching orders...")
        .hide();
      var $previewEmptyMsg = $("<div>")
        .addClass("preview-empty-message")
        .text("No orders match the selected statuses.")
        .hide();
      var $previewBtn = $("<button>")
        .attr("type", "button")
        .addClass("button wc-data-cleanup-load-status-preview")
        .text("Preview Matching Orders")
        .prop("disabled", true);
      var $deleteBtn = $("<button>")
        .attr("type", "button")
        .addClass("button button-primary wc-data-cleanup-delete-by-status")
        .text("Delete Orders with Selected Status")
        .prop("disabled", true);

      // Replace dropdown with our checkbox container
      $statusDropdown
        .hide()
        .after(
          $statusContainer.append(
            $statusDescription,
            $statusCheckboxContainer.append($statusLoadingMsg),
            $previewBtn,
            $deleteBtn,
            $previewContainer.append(
              $previewHeading,
              $previewList,
              $previewLoadingMsg.hide(),
              $previewEmptyMsg
            )
          )
        );

      // Load statuses with accurate counts - direct query
      getOrderStatuses(function (response) {
        $statusLoadingMsg.hide();
        displayOrderStatuses(response, $statusCheckboxContainer);
      });
    }

    // Function to get order statuses with counts
    function getOrderStatuses(callback) {
      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        dataType: "json",
        data: {
          action: "wc_data_cleanup_get_order_statuses",
          nonce: wc_data_cleanup_params.nonce,
          force_refresh: true, // Force a fresh count
        },
        success: function (response) {
          if (callback && typeof callback === "function") {
            callback(response);
          }
        },
        error: function () {
          if (callback && typeof callback === "function") {
            callback([]);
          }
        },
      });
    }

    // Function to display order statuses
    function displayOrderStatuses(response, $container) {
      $container.empty(); // Clear existing checkboxes

      if (response && response.length) {
        // Create a checkbox for each status
        $.each(response, function (i, status) {
          // Include all statuses, even those with 0 orders
          var $checkbox = $("<input>")
            .attr({
              type: "checkbox",
              id: "status-" + status.id,
              name: "order_status[]",
              value: status.id,
            })
            .data("count", status.count);

          var $label = $("<label>")
            .attr("for", "status-" + status.id)
            .html(status.text + " <span>(" + status.count + ")</span>");

          var $checkboxWrapper = $("<div>")
            .addClass("status-checkbox-wrapper")
            .append($checkbox, $label);

          $container.append($checkboxWrapper);
        });

        // Add event handlers
        $container.on("change", "input[type=checkbox]", function () {
          var anyChecked =
            $container.find("input[type=checkbox]:checked").length > 0;

          // Find buttons inside the container's parent
          var $parent = $container.parent();
          var $previewBtn = $parent.find(
            ".wc-data-cleanup-load-status-preview"
          );
          var $deleteBtn = $parent.find(".wc-data-cleanup-delete-by-status");

          $previewBtn.prop("disabled", !anyChecked);
          $deleteBtn.prop("disabled", !anyChecked);

          // Clear preview when selection changes
          var $previewList = $parent.find(".order-preview-list");
          var $previewContainer = $parent.find(
            ".wc-data-cleanup-status-preview"
          );
          $previewList.empty();
          $previewContainer.hide();
        });

        // Find buttons inside the container's parent
        var $parent = $container.parent();
        var $previewBtn = $parent.find(".wc-data-cleanup-load-status-preview");
        var $deleteBtn = $parent.find(".wc-data-cleanup-delete-by-status");

        // Load preview of matching orders
        $previewBtn.on("click", function () {
          var $container = $(".status-checkbox-container");
          var selectedStatuses = [];
          $container.find("input[type=checkbox]:checked").each(function () {
            selectedStatuses.push($(this).val());
          });

          if (selectedStatuses.length === 0) {
            return;
          }

          var $parent = $(this).parent();
          var $previewList = $parent.find(".order-preview-list");
          var $previewLoadingMsg = $parent.find(".preview-loading-message");
          var $previewEmptyMsg = $parent.find(".preview-empty-message");
          var $previewContainer = $parent.find(
            ".wc-data-cleanup-status-preview"
          );

          $previewList.empty();
          $previewLoadingMsg.show();
          $previewEmptyMsg.hide();
          $previewContainer.show();

          // Load orders matching selected statuses
          $.ajax({
            url: wc_data_cleanup_params.ajax_url,
            dataType: "json",
            data: {
              action: "wc_data_cleanup_get_orders",
              nonce: wc_data_cleanup_params.nonce,
              status: selectedStatuses.join(","),
              preview: true,
              limit: 10, // Only get first 10 for preview
            },
            success: function (data) {
              $previewLoadingMsg.hide();

              // Debug info
              console.log("Preview data received:", data);

              if (data && data.results && data.results.length > 0) {
                $.each(data.results, function (i, order) {
                  var $orderItem = $("<li>").html(
                    "<strong>" +
                      order.text +
                      "</strong> " +
                      (order.date
                        ? "<span>Date: " + order.date + "</span> "
                        : "") +
                      (order.total
                        ? "<span>Total: " + order.total + "</span>"
                        : "")
                  );
                  $previewList.append($orderItem);
                });

                // Add a message about how many more
                if (
                  data.total_count &&
                  data.total_count > data.results.length
                ) {
                  var moreCount = data.total_count - data.results.length;
                  var $moreItem = $("<li>")
                    .addClass("more-message")
                    .text("... and " + moreCount + " more order(s)");
                  $previewList.append($moreItem);
                }
              } else {
                console.log("No orders found or invalid data structure");
                $previewEmptyMsg.show();
              }
            },
            error: function () {
              $previewLoadingMsg.hide();
              $previewEmptyMsg
                .text("Error loading orders. Please try again.")
                .show();
            },
          });
        });

        // Handle delete button click
        $deleteBtn.on("click", function () {
          var $container = $(".status-checkbox-container");
          var selectedStatuses = [];
          $container.find("input[type=checkbox]:checked").each(function () {
            selectedStatuses.push($(this).val());
          });

          if (selectedStatuses.length === 0) {
            return;
          }

          if (confirm(wc_data_cleanup_params.confirm_delete_all)) {
            deleteOrders(
              "delete_by_status",
              [],
              selectedStatuses.join(","),
              "",
              "",
              {}
            );
          }
        });
      } else {
        $container.append(
          $("<p>")
            .addClass("no-statuses-message")
            .text("No order statuses found.")
        );
      }
    }

    // Call the function to replace the dropdown
    if ($("#wc-data-cleanup-order-status").length > 0) {
      convertOrderStatusToCheckboxes();
    }

    // Improve date range selector functionality
    function enhanceDatePicker() {
      var $dateFrom = $("#wc-data-cleanup-date-from");
      var $dateTo = $("#wc-data-cleanup-date-to");
      var $dateContainer = $dateFrom.closest(".wc-data-cleanup-action-group");
      var $previewContainer = $("<div>")
        .addClass("wc-data-cleanup-date-preview")
        .hide();
      var $previewHeading = $("<h5>").text("Orders in selected date range:");
      var $previewList = $("<ul>").addClass("order-preview-list");
      var $previewLoadingMsg = $("<div>")
        .addClass("preview-loading-message")
        .text("Loading matching orders...")
        .hide();
      var $previewEmptyMsg = $("<div>")
        .addClass("preview-empty-message")
        .text("No orders found in this date range.")
        .hide();
      var $previewBtn = $("<button>")
        .attr("type", "button")
        .addClass("button wc-data-cleanup-load-date-preview")
        .text("Preview Orders in Date Range");

      // Add preview section
      $dateContainer
        .find(".wc-data-cleanup-delete-by-date-range")
        .before($previewBtn);
      $dateContainer.append(
        $previewContainer.append(
          $previewHeading,
          $previewList,
          $previewLoadingMsg,
          $previewEmptyMsg
        )
      );

      // Make date inputs open the calendar when clicked anywhere on the input
      $dateFrom
        .add($dateTo)
        .on("click", function () {
          // Trigger native date picker by programmatically focusing and clicking
          $(this).focus();
          // Create and trigger a "mousedown" event on the input to open the datepicker
          var evt = document.createEvent("MouseEvents");
          evt.initMouseEvent(
            "mousedown",
            true,
            true,
            window,
            0,
            0,
            0,
            0,
            0,
            false,
            false,
            false,
            false,
            0,
            null
          );
          this.dispatchEvent(evt);
        })
        // Handle the calendar icon click as well
        .next(".dashicons-calendar-alt")
        .on("click", function () {
          $(this).prev("input").click();
        });

      // Preview button handler
      $previewBtn.on("click", function () {
        var dateFrom = $dateFrom.val();
        var dateTo = $dateTo.val();

        if (!dateFrom || !dateTo) {
          alert("Please select both start and end dates.");
          return;
        }

        $previewList.empty();
        $previewLoadingMsg.show();
        $previewEmptyMsg.hide();
        $previewContainer.show();

        // Load orders in date range
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_orders",
            nonce: wc_data_cleanup_params.nonce,
            date_from: dateFrom,
            date_to: dateTo,
            preview: true,
            limit: 10, // Only get first 10 for preview
          },
          success: function (data) {
            $previewLoadingMsg.hide();

            if (data && data.results && data.results.length > 0) {
              $.each(data.results, function (i, order) {
                var $orderItem = $("<li>").html(
                  "<strong>" +
                    order.text +
                    "</strong> " +
                    (order.date
                      ? "<span>Date: " + order.date + "</span> "
                      : "") +
                    (order.total
                      ? "<span>Total: " + order.total + "</span>"
                      : "")
                );
                $previewList.append($orderItem);
              });

              // Add a message about how many more
              if (data.total_count && data.total_count > data.results.length) {
                var moreCount = data.total_count - data.results.length;
                var $moreItem = $("<li>")
                  .addClass("more-message")
                  .text("... and " + moreCount + " more order(s)");
                $previewList.append($moreItem);
              }
            } else {
              $previewEmptyMsg.show();
            }
          },
          error: function () {
            $previewLoadingMsg.hide();
            $previewEmptyMsg
              .text("Error loading orders. Please try again.")
              .show();
          },
        });
      });
    }

    // Enhance date picker functionality
    if ($("#wc-data-cleanup-date-from").length > 0) {
      enhanceDatePicker();
    }

    // Force native datepicker to appear when clicking on date inputs
    // Direct implementation without waiting for the enhanceDatePicker function
    $(".wc-data-cleanup-date").on("click", function () {
      // Directly open the date picker
      this.showPicker();
    });

    // Calendar icon click handler
    $(".dashicons-calendar-alt").on("click", function () {
      // Find the date input before this icon and open its picker
      $(this).prev("input").trigger("click");
    });

    // Initialize the order status count function - this will refresh the counts
    function refreshOrderStatusCounts() {
      // Make AJAX call to get updated counts
      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        dataType: "json",
        data: {
          action: "wc_data_cleanup_get_order_statuses",
          nonce: wc_data_cleanup_params.nonce,
        },
        success: function (response) {
          if (response && response.length) {
            // Update counts for each status checkbox
            $.each(response, function (i, status) {
              var $checkbox = $("#status-" + status.id);
              if ($checkbox.length) {
                // Update the label with the new count
                $checkbox
                  .parent()
                  .find("label span")
                  .text("(" + status.count + ")");
                // Update the data attribute
                $checkbox.data("count", status.count);
              }
            });
          }
        },
      });
    }

    // Call once on page load to ensure counts are accurate
    if ($(".status-checkbox-container").length > 0) {
      setTimeout(refreshOrderStatusCounts, 1000);
    }

    // Format user results with indicators
    function formatUserResult(user) {
      if (!user.id) {
        return user.text;
      }

      var $result = $("<span></span>");
      var indicators = "";
      var hasData = false;

      // Only add indicators if has_orders is defined (means data was loaded)
      if (typeof user.has_orders !== "undefined") {
        hasData = true;

        if (user.has_orders) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders" title="Has orders"></span>';
        }

        if (user.has_posts) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-posts" title="Has posts"></span>';
        }

        if (user.has_comments) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-comments" title="Has comments"></span>';
        }
      }

      if (indicators) {
        $result.append(
          $(
            '<span class="select2-selection__choice__data-indicators">' +
              indicators +
              "</span>"
          )
        );
      }

      // Replace [Admin] text with styled indicator if present
      var userText = user.text;
      if (user.is_admin || userText.indexOf("[Admin:") > -1) {
        // Extract admin name if available
        var adminName = user.admin_name || "";
        if (!adminName && userText.indexOf("[Admin:") > -1) {
          var matches = userText.match(/\[Admin:\s*([^\]]+)\]/);
          if (matches && matches[1]) {
            adminName = matches[1].trim();
          } else {
            adminName = "Admin";
          }
        }

        userText = userText.replace(/\[Admin:.*?\]/, "");
        $result.append(userText);
        $result.append(
          $(
            '<span class="admin-user-indicator">Admin: ' + adminName + "</span>"
          )
        );
      } else {
        $result.append(userText);
      }

      // If data hasn't been loaded yet, add a loading indicator for selected items
      if (!hasData && $(".select2-selection--multiple").length > 0) {
        $result.append(
          '<span class="select2-selection__data-loading"> (loading data...)</span>'
        );
      }

      return $result;
    }

    // Format user selection with indicators
    function formatUserSelection(user) {
      if (!user.id) {
        return user.text;
      }

      var $result = $("<span></span>");
      var indicators = "";
      var hasData = false;

      // Only add indicators if has_orders is defined (means data was loaded)
      if (typeof user.has_orders !== "undefined") {
        hasData = true;

        if (user.has_orders) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders" title="Has orders"></span>';
        }

        if (user.has_posts) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-posts" title="Has posts"></span>';
        }

        if (user.has_comments) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-comments" title="Has comments"></span>';
        }
      }

      if (indicators) {
        $result.append(
          $(
            '<span class="select2-selection__choice__data-indicators">' +
              indicators +
              "</span>"
          )
        );
      }

      // Replace [Admin] text with styled indicator if present
      var userText = user.text;
      if (user.is_admin || userText.indexOf("[Admin:") > -1) {
        // Extract admin name if available
        var adminName = user.admin_name || "";
        if (!adminName && userText.indexOf("[Admin:") > -1) {
          var matches = userText.match(/\[Admin:\s*([^\]]+)\]/);
          if (matches && matches[1]) {
            adminName = matches[1].trim();
          } else {
            adminName = "Admin";
          }
        }

        userText = userText.replace(/\[Admin:.*?\]/, "");
        $result.append(userText);
        $result.append(
          $(
            '<span class="admin-user-indicator">Admin: ' + adminName + "</span>"
          )
        );
      } else {
        $result.append(userText);
      }

      // If data hasn't been loaded yet, add a loading indicator for selected items
      if (!hasData && $(".select2-selection--multiple").length > 0) {
        // Attempt to load the full user data if it's not loaded yet
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_users",
            search: user.id, // Search by ID for an exact match
            nonce: wc_data_cleanup_params.nonce,
            include_data: true,
          },
          success: function (data) {
            if (data && data.results && data.results.length > 0) {
              // Update the data in Select2
              var userData = data.results[0];

              // Update the option in the select element
              var option = $("#wc-data-cleanup-user-select").find(
                'option[value="' + user.id + '"]'
              );
              option.data("has_orders", userData.has_orders);
              option.data("has_posts", userData.has_posts);
              option.data("has_comments", userData.has_comments);
              option.data("is_admin", userData.is_admin);
              option.data("admin_name", userData.admin_name);

              // Trigger a change to refresh the display
              $("#wc-data-cleanup-user-select").trigger("change");
            }
          },
        });
      }

      return $result;
    }

    // Format customer results with indicators
    function formatCustomerResult(customer) {
      if (!customer.id) {
        return customer.text;
      }

      var $result = $("<span></span>");
      var indicators = "";
      var hasData = false;

      // Only add indicators if has_orders is defined (means data was loaded)
      if (typeof customer.has_orders !== "undefined") {
        hasData = true;

        if (customer.has_orders) {
          indicators +=
            '<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders" title="Has orders"></span>';

          // If we have order count data, add it
          if (customer.order_count) {
            indicators +=
              ' <span class="wc-data-cleanup-order-count">(' +
              customer.order_count +
              " orders)</span>";
          }
        }
      }

      if (indicators) {
        $result.append(
          $(
            '<span class="select2-selection__choice__data-indicators">' +
              indicators +
              "</span>"
          )
        );
      }

      // Replace [Admin] text with styled indicator if present
      var customerText = customer.text;
      if (customer.is_admin || customerText.indexOf("[Admin:") > -1) {
        // Extract admin name if available
        var adminName = customer.admin_name || "";
        if (!adminName && customerText.indexOf("[Admin:") > -1) {
          var matches = customerText.match(/\[Admin:\s*([^\]]+)\]/);
          if (matches && matches[1]) {
            adminName = matches[1].trim();
          } else {
            adminName = "Admin";
          }
        }

        customerText = customerText.replace(/\[Admin:.*?\]/, "");
        $result.append(customerText);
        $result.append(
          $(
            '<span class="admin-user-indicator">Admin: ' + adminName + "</span>"
          )
        );
      } else {
        $result.append(customerText);
      }

      // If data hasn't been loaded yet, add a loading indicator for selected items
      if (!hasData && $(".select2-selection--multiple").length > 0) {
        $result.append(
          '<span class="select2-selection__data-loading"> (loading data...)</span>'
        );

        // Attempt to load the full customer data if it's not loaded yet
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_customers",
            search: customer.id, // Search by ID for an exact match
            nonce: wc_data_cleanup_params.nonce,
            include_data: true,
          },
          success: function (data) {
            if (data && data.results && data.results.length > 0) {
              // Update the data in Select2
              var customerData = data.results[0];

              // Update the option in the select element
              var option = $("#wc-data-cleanup-customer-select").find(
                'option[value="' + customer.id + '"]'
              );
              option.data("has_orders", customerData.has_orders);
              option.data("order_count", customerData.order_count);
              option.data("is_admin", customerData.is_admin);
              option.data("admin_name", customerData.admin_name);

              // Trigger a change to refresh the display
              $("#wc-data-cleanup-customer-select").trigger("change");
            }
          },
        });
      }

      return $result;
    }

    // Format order results
    function formatOrderResult(order) {
      if (!order.id) {
        return order.text;
      }

      var $result = $("<div></div>");

      // Replace [Admin] text with styled indicator if present
      var orderText = order.text;
      if (order.is_admin || orderText.indexOf("[Admin:") > -1) {
        // Extract admin name if available
        var adminName = order.admin_name || "";
        if (!adminName && orderText.indexOf("[Admin:") > -1) {
          var matches = orderText.match(/\[Admin:\s*([^\]]+)\]/);
          if (matches && matches[1]) {
            adminName = matches[1].trim();
          } else {
            adminName = "Admin";
          }
        }

        orderText = orderText.replace(/\[Admin:.*?\]/, "");
        $result.append(
          $(
            "<div><strong>" +
              orderText +
              "</strong> <span class='admin-user-indicator'>Admin: " +
              adminName +
              "</span></div>"
          )
        );
      } else {
        $result.append($("<div><strong>" + orderText + "</strong></div>"));
      }

      // Add details if available
      if (order.date && order.status) {
        var detailsLine =
          "<div><small>Date: " + order.date + " | Status: " + order.status;

        if (order.total) {
          detailsLine += " | Total: " + order.total;
        }

        // Add customer email if available
        if (order.customer_email) {
          detailsLine += " | Email: " + order.customer_email;
        }

        detailsLine += "</small></div>";

        $result.append($(detailsLine));
      } else if ($(".select2-selection--multiple").length > 0) {
        // For selected items without details, try to load them
        $result.append($("<div><small>Loading order details...</small></div>"));

        // Attempt to load the full order data if it's not loaded yet
        $.ajax({
          url: wc_data_cleanup_params.ajax_url,
          dataType: "json",
          data: {
            action: "wc_data_cleanup_get_orders",
            search: order.id, // Search by ID for an exact match
            nonce: wc_data_cleanup_params.nonce,
            include_details: true,
          },
          success: function (data) {
            if (data && data.results && data.results.length > 0) {
              // Update the data in Select2
              var orderData = data.results[0];

              // Update the option in the select element
              var option = $("#wc-data-cleanup-order-select").find(
                'option[value="' + order.id + '"]'
              );
              option.data("date", orderData.date);
              option.data("status", orderData.status);
              option.data("total", orderData.total);
              option.data("customer_email", orderData.customer_email);
              option.data("is_admin", orderData.is_admin);
              option.data("admin_name", orderData.admin_name);

              // Trigger a change to refresh the display
              $("#wc-data-cleanup-order-select").trigger("change");
            }
          },
        });
      }

      return $result;
    }

    // Add modal HTML to the page
    $("body").append(`
      <div class="wc-data-cleanup-modal-overlay"></div>
      <div class="wc-data-cleanup-modal">
        <div class="wc-data-cleanup-modal-header">
          <h3 class="wc-data-cleanup-modal-title">Confirm Action</h3>
          <span class="wc-data-cleanup-modal-close">&times;</span>
        </div>
        <div class="wc-data-cleanup-modal-body"></div>
        <div class="wc-data-cleanup-modal-footer">
          <button type="button" class="button wc-data-cleanup-modal-cancel">Cancel</button>
          <button type="button" class="button button-primary wc-data-cleanup-modal-confirm">Confirm</button>
        </div>
      </div>
    `);

    // Close modal on click
    $(".wc-data-cleanup-modal-close, .wc-data-cleanup-modal-cancel").on(
      "click",
      function () {
        closeModal();
      }
    );

    // Close modal on escape key
    $(document).on("keyup", function (e) {
      if (e.key === "Escape") {
        closeModal();
      }
    });

    // Close modal when clicking outside
    $(".wc-data-cleanup-modal-overlay").on("click", function () {
      closeModal();
    });

    // User actions
    $(".wc-data-cleanup-delete-all-users").on("click", function () {
      showModal(
        "Delete All Customer Users",
        "Are you sure you want to delete all WordPress users with the customer role? This action cannot be undone.",
        function () {
          deleteUsers("delete_all");
        }
      );
    });

    $(".wc-data-cleanup-delete-selected-users").on("click", function () {
      var selectedUsers = $("#wc-data-cleanup-user-select").val();
      if (selectedUsers && selectedUsers.length > 0) {
        // Get selected user data
        var selectedOptions = $("#wc-data-cleanup-user-select").select2("data");
        var usersWithData = {
          orders: [],
          posts: [],
          comments: [],
        };

        // Check for users with associated data
        selectedOptions.forEach(function (option) {
          if (option.has_orders) {
            usersWithData.orders.push(option.text);
          }
          if (option.has_posts) {
            usersWithData.posts.push(option.text);
          }
          if (option.has_comments) {
            usersWithData.comments.push(option.text);
          }
        });

        var modalContent =
          "Are you sure you want to delete the selected users? This action cannot be undone.";
        var modalOptions = "";

        // Add warnings about users with data
        if (
          usersWithData.orders.length > 0 ||
          usersWithData.posts.length > 0 ||
          usersWithData.comments.length > 0
        ) {
          modalContent += '<div class="wc-data-cleanup-data-details">';

          if (usersWithData.orders.length > 0) {
            modalContent +=
              '<h4><span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> ' +
              usersWithData.orders.length +
              " selected users have orders:</h4>" +
              '<div class="wc-data-cleanup-data-list"><ul><li>' +
              usersWithData.orders.join("</li><li>") +
              "</li></ul></div>";
          }

          if (usersWithData.posts.length > 0) {
            modalContent +=
              '<h4><span class="wc-data-cleanup-has-data wc-data-cleanup-has-posts"></span> ' +
              usersWithData.posts.length +
              " selected users have posts:</h4>" +
              '<div class="wc-data-cleanup-data-list"><ul><li>' +
              usersWithData.posts.join("</li><li>") +
              "</li></ul></div>";
          }

          if (usersWithData.comments.length > 0) {
            modalContent +=
              '<h4><span class="wc-data-cleanup-has-data wc-data-cleanup-has-comments"></span> ' +
              usersWithData.comments.length +
              " selected users have comments:</h4>" +
              '<div class="wc-data-cleanup-data-list"><ul><li>' +
              usersWithData.comments.join("</li><li>") +
              "</li></ul></div>";
          }

          modalContent += "</div>";

          // Add options for handling associated data
          modalOptions = `
            <div class="wc-data-cleanup-options">
              <div class="wc-data-cleanup-option">
                <label for="wc-data-cleanup-handle-orders">How to handle user orders:</label>
                <select id="wc-data-cleanup-handle-orders">
                  <option value="skip">Skip users with orders</option>
                  <option value="delete">Delete users and their orders</option>
                </select>
              </div>
              
              <div class="wc-data-cleanup-option">
                <label for="wc-data-cleanup-handle-posts">How to handle user posts:</label>
                <select id="wc-data-cleanup-handle-posts">
                  <option value="reassign">Reassign posts to another user</option>
                  <option value="delete">Delete posts along with users</option>
                </select>
              </div>
              
              <div class="wc-data-cleanup-option" id="wc-data-cleanup-reassign-container">
                <label for="wc-data-cleanup-reassign-user">Reassign content to:</label>
                <select id="wc-data-cleanup-reassign-user">
                  <option value="1">Admin</option>
                </select>
              </div>
            </div>
          `;
        }

        showModal(
          "Delete Selected Users",
          modalContent,
          function () {
            var options = {
              force_delete:
                $("#wc-data-cleanup-handle-orders").val() === "delete",
              delete_orders:
                $("#wc-data-cleanup-handle-orders").val() === "delete",
              reassign_posts:
                $("#wc-data-cleanup-handle-posts").val() === "delete"
                  ? 0
                  : parseInt($("#wc-data-cleanup-reassign-user").val(), 10),
            };
            deleteUsers("delete_selected", selectedUsers, options);
          },
          modalOptions
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    $(".wc-data-cleanup-delete-except-users").on("click", function () {
      var selectedUsers = $("#wc-data-cleanup-user-select").val();
      if (selectedUsers && selectedUsers.length > 0) {
        showModal(
          "Delete All Except Selected Users",
          "Are you sure you want to delete all users except the selected ones? This action cannot be undone.",
          function () {
            deleteUsers("delete_except", selectedUsers);
          }
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    // Customer actions
    $(".wc-data-cleanup-delete-all-customers").on("click", function () {
      showModal(
        "Delete All Customers",
        "Are you sure you want to delete all WooCommerce customers? This action cannot be undone.",
        function () {
          deleteCustomers("delete_all");
        }
      );
    });

    $(".wc-data-cleanup-delete-selected-customers").on("click", function () {
      var selectedCustomers = $("#wc-data-cleanup-customer-select").val();
      if (selectedCustomers && selectedCustomers.length > 0) {
        // Get selected customer data
        var selectedOptions = $("#wc-data-cleanup-customer-select").select2(
          "data"
        );
        var customersWithOrders = [];

        // Check for customers with orders
        selectedOptions.forEach(function (option) {
          if (option.has_orders) {
            customersWithOrders.push(option.text);
          }
        });

        var modalContent =
          "Are you sure you want to delete the selected customers? This action cannot be undone.";
        var modalOptions = "";

        // Add warnings about customers with orders
        if (customersWithOrders.length > 0) {
          modalContent += '<div class="wc-data-cleanup-data-details">';
          modalContent +=
            '<h4><span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> ' +
            customersWithOrders.length +
            " selected customers have orders:</h4>" +
            '<div class="wc-data-cleanup-data-list"><ul><li>' +
            customersWithOrders.join("</li><li>") +
            "</li></ul></div>";
          modalContent += "</div>";

          // Add options for handling orders
          modalOptions = `
            <div class="wc-data-cleanup-options">
              <div class="wc-data-cleanup-option">
                <label for="wc-data-cleanup-handle-customer-orders">How to handle customer orders:</label>
                <select id="wc-data-cleanup-handle-customer-orders">
                  <option value="skip">Skip customers with orders</option>
                  <option value="delete">Delete customers and their orders</option>
                </select>
              </div>
            </div>
          `;
        }

        showModal(
          "Delete Selected Customers",
          modalContent,
          function () {
            var options = {
              force_delete:
                $("#wc-data-cleanup-handle-customer-orders").val() === "delete",
              delete_orders:
                $("#wc-data-cleanup-handle-customer-orders").val() === "delete",
            };
            deleteCustomers("delete_selected", selectedCustomers, options);
          },
          modalOptions
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    $(".wc-data-cleanup-delete-except-customers").on("click", function () {
      var selectedCustomers = $("#wc-data-cleanup-customer-select").val();
      if (selectedCustomers && selectedCustomers.length > 0) {
        showModal(
          "Delete All Except Selected Customers",
          "Are you sure you want to delete all customers except the selected ones? This action cannot be undone.",
          function () {
            deleteCustomers("delete_except", selectedCustomers);
          }
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    // Order actions
    $(".wc-data-cleanup-delete-all-orders").on("click", function () {
      showModal(
        "Delete All Orders",
        "Are you sure you want to delete all WooCommerce orders? This action cannot be undone.",
        function () {
          deleteOrders("delete_all");
        }
      );
    });

    $(".wc-data-cleanup-delete-selected-orders").on("click", function () {
      var selectedOrders = $("#wc-data-cleanup-order-select").val();
      if (selectedOrders && selectedOrders.length > 0) {
        showModal(
          "Delete Selected Orders",
          "Are you sure you want to delete the selected orders? This action cannot be undone.",
          function () {
            deleteOrders("delete_selected", selectedOrders);
          }
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    $(".wc-data-cleanup-delete-except-orders").on("click", function () {
      var selectedOrders = $("#wc-data-cleanup-order-select").val();
      if (selectedOrders && selectedOrders.length > 0) {
        showModal(
          "Delete All Except Selected Orders",
          "Are you sure you want to delete all orders except the selected ones? This action cannot be undone.",
          function () {
            deleteOrders("delete_except", selectedOrders);
          }
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    $(".wc-data-cleanup-delete-by-status").on("click", function () {
      var status = $("#wc-data-cleanup-order-status").val();
      var statusText = $(
        "#wc-data-cleanup-order-status option:selected"
      ).text();
      if (status) {
        showModal(
          "Delete Orders by Status",
          'Are you sure you want to delete all orders with status "' +
            statusText +
            '"? This action cannot be undone.',
          function () {
            deleteOrders("delete_by_status", [], status);
          }
        );
      } else {
        alert(wc_data_cleanup_params.error_no_selection);
      }
    });

    $(".wc-data-cleanup-delete-by-date-range").on("click", function () {
      var dateFrom = $("#wc-data-cleanup-date-from").val();
      var dateTo = $("#wc-data-cleanup-date-to").val();

      if (dateFrom && dateTo) {
        showModal(
          "Delete Orders by Date Range",
          "Are you sure you want to delete all orders between " +
            dateFrom +
            " and " +
            dateTo +
            "? This action cannot be undone.",
          function () {
            deleteOrders("delete_by_date_range", [], null, dateFrom, dateTo);
          }
        );
      } else {
        alert("Please select both start and end dates.");
      }
    });

    /**
     * Show modal dialog
     *
     * @param {string} title       Modal title
     * @param {string} content     Modal content
     * @param {function} callback  Callback function on confirm
     * @param {string} options     Optional HTML for options
     */
    function showModal(title, content, callback, options) {
      // Set modal title and content
      $(".wc-data-cleanup-modal-title").text(title);
      $(".wc-data-cleanup-modal-body").html(content);

      // Add options if provided
      if (options) {
        $(".wc-data-cleanup-modal-body").append(options);
      }

      // Set confirm button action
      $(".wc-data-cleanup-modal-confirm")
        .off("click")
        .on("click", function () {
          closeModal();
          callback();
        });

      // Show modal
      $(".wc-data-cleanup-modal-overlay").fadeIn(200);
      $(".wc-data-cleanup-modal").fadeIn(200);
    }

    /**
     * Close modal dialog
     */
    function closeModal() {
      $(".wc-data-cleanup-modal-overlay").fadeOut(200);
      $(".wc-data-cleanup-modal").fadeOut(200);
    }

    /**
     * Delete users via AJAX
     *
     * @param {string} actionType Type of delete action
     * @param {Array}  userIds    Array of user IDs (optional)
     * @param {Object} options    Additional options
     */
    function deleteUsers(actionType, userIds, options) {
      showSpinner();
      clearMessage();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_users",
          nonce: wc_data_cleanup_params.nonce,
          action_type: actionType,
          user_ids: userIds || [],
          options: options || {},
        },
        success: function (response) {
          hideSpinner();
          if (response.success) {
            showSuccessMessage(response.data.message);
            // Reset selection - properly destroy and reinitialize
            $("#wc-data-cleanup-user-select").val(null).trigger("change");
            // Clear the Select2 cache
            $("#wc-data-cleanup-user-select").empty();
          } else {
            showErrorMessage(response.data.message);
          }
        },
        error: function () {
          hideSpinner();
          showErrorMessage(wc_data_cleanup_params.error);
        },
      });
    }

    /**
     * Delete customers via AJAX
     *
     * @param {string} actionType   Type of delete action
     * @param {Array}  customerIds  Array of customer IDs (optional)
     * @param {Object} options      Additional options
     */
    function deleteCustomers(actionType, customerIds, options) {
      showSpinner();
      clearMessage();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_customers",
          nonce: wc_data_cleanup_params.nonce,
          action_type: actionType,
          customer_ids: customerIds || [],
          options: options || {},
        },
        success: function (response) {
          hideSpinner();
          if (response.success) {
            showSuccessMessage(response.data.message);
            // Reset selection - properly destroy and reinitialize
            $("#wc-data-cleanup-customer-select").val(null).trigger("change");
            // Clear the Select2 cache
            $("#wc-data-cleanup-customer-select").empty();
          } else {
            showErrorMessage(response.data.message);
          }
        },
        error: function () {
          hideSpinner();
          showErrorMessage(wc_data_cleanup_params.error);
        },
      });
    }

    /**
     * Delete orders via AJAX
     *
     * @param {string} actionType  Type of delete action
     * @param {Array}  orderIds    Array of order IDs (optional)
     * @param {string} orderStatus Order status (optional)
     * @param {string} dateFrom    Start date (optional)
     * @param {string} dateTo      End date (optional)
     * @param {Object} options     Additional options
     */
    function deleteOrders(
      actionType,
      orderIds,
      orderStatus,
      dateFrom,
      dateTo,
      options
    ) {
      showSpinner();
      clearMessage();

      // Add progress bar for batch processing
      $(".wc-data-cleanup-results").append(
        '<div class="wc-data-cleanup-progress">' +
          '<div class="wc-data-cleanup-progress-bar-container">' +
          '<div class="wc-data-cleanup-progress-bar" style="width: 0%"></div>' +
          "</div>" +
          '<div class="wc-data-cleanup-progress-text">Processing: 0%</div>' +
          "</div>"
      );

      $(".wc-data-cleanup-progress").show();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_orders",
          nonce: wc_data_cleanup_params.nonce,
          action_type: actionType,
          order_ids: orderIds || [],
          order_status: orderStatus || "",
          date_from: dateFrom || "",
          date_to: dateTo || "",
          options: options || {},
        },
        success: function (response) {
          hideSpinner();
          $(".wc-data-cleanup-progress").hide();

          if (response.success) {
            showSuccessMessage(response.data.message);
            // Reset selection - properly destroy and reinitialize
            $("#wc-data-cleanup-order-select").val(null).trigger("change");
            // Clear the Select2 cache
            $("#wc-data-cleanup-order-select").empty();
          } else {
            showErrorMessage(response.data.message);
          }
        },
        error: function () {
          hideSpinner();
          $(".wc-data-cleanup-progress").hide();
          showErrorMessage(wc_data_cleanup_params.error);
        },
      });
    }

    /**
     * Show spinner
     */
    function showSpinner() {
      $(".wc-data-cleanup-spinner").css("visibility", "visible");
    }

    /**
     * Hide spinner
     */
    function hideSpinner() {
      $(".wc-data-cleanup-spinner").css("visibility", "hidden");
    }

    /**
     * Clear message
     */
    function clearMessage() {
      $(".wc-data-cleanup-message")
        .removeClass("updated")
        .removeClass("error")
        .empty();
    }

    /**
     * Show success message
     *
     * @param {string} message Message to show
     */
    function showSuccessMessage(message) {
      $(".wc-data-cleanup-message")
        .addClass("updated")
        .html("<p>" + message + "</p>");
    }

    /**
     * Show error message
     *
     * @param {string} message Message to show
     */
    function showErrorMessage(message) {
      $(".wc-data-cleanup-message")
        .addClass("error")
        .html("<p>" + message + "</p>");
    }
  });
})(jQuery);
