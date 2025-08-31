/**
 * Products Tab JavaScript
 */
(function ($) {
  "use strict";

  jQuery(document).ready(function ($) {
    var allDuplicates = []; // Store all scanned duplicates
    var currentFilter = "all";
    var selectedProducts = [];
    var currentPage = 1;
    var itemsPerPage = 20;

    // Ensure scanner section is visible on page load
    $(".wc-data-cleanup-scanner-section").show();

    // Show/hide loading
    function showLoading() {
      $(".wc-data-cleanup-loading").show();
    }

    function hideLoading() {
      $(".wc-data-cleanup-loading").hide();
    }

    // Show message
    function showMessage(type, message) {
      var messageHtml =
        '<div class="notice notice-' +
        type +
        ' is-dismissible"><p>' +
        message +
        "</p></div>";
      $(".wc-data-cleanup-messages").html(messageHtml);

      // Auto-dismiss after 7 seconds
      setTimeout(function () {
        $(".wc-data-cleanup-messages .notice").fadeOut();
      }, 7000);
    }

    // Scan for duplicates
    function scanDuplicates() {
      showLoading();

      console.log("Starting scan with params:", {
        url: wc_data_cleanup_params.ajax_url,
        nonce: wc_data_cleanup_params.nonce,
      });

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_scan_duplicates",
          nonce: wc_data_cleanup_params.nonce,
        },
        success: function (response) {
          console.log("Scan response:", response);
          hideLoading();

          if (response.success && response.data) {
            allDuplicates = response.data.products || [];
            console.log("Found duplicates:", allDuplicates);

            if (allDuplicates.length > 0) {
              // Count unique product groups
              var uniqueGroups = {};
              allDuplicates.forEach(function (item) {
                var key = item.type + ":" + item.duplicate_value;
                if (!uniqueGroups[key]) {
                  uniqueGroups[key] = 0;
                }
                uniqueGroups[key]++;
              });

              var groupCount = Object.keys(uniqueGroups).length;
              showMessage(
                "success",
                "Found " +
                  allDuplicates.length +
                  " duplicate products in " +
                  groupCount +
                  " groups"
              );
              displayResults(allDuplicates);
              // Hide the entire scanner section
              $(".wc-data-cleanup-scanner-section").slideUp();
            } else {
              showMessage(
                "info",
                "No duplicate products found. Your products are unique!"
              );
              $(".wc-data-cleanup-results-section").hide();
            }
          } else {
            var errorMsg =
              response.data && response.data.message
                ? response.data.message
                : "Failed to scan for duplicates";
            showMessage("error", errorMsg);
          }
        },
        error: function (xhr, status, error) {
          hideLoading();
          console.error("Scan error:", status, error, xhr.responseText);
          showMessage(
            "error",
            "An error occurred during the scan. Check console for details."
          );
        },
      });
    }

    // Display results with filters
    function displayResults(products, page) {
      page = page || 1;
      currentPage = page;

      // Calculate counts for filters
      var counts = {
        all: products.length,
        sku: products.filter((p) => p.type === "sku").length,
        title: products.filter((p) => p.type === "title").length,
        barcode: products.filter((p) => p.type === "barcode").length,
      };

      // Calculate pagination
      var totalPages = Math.ceil(products.length / itemsPerPage);
      var start = (currentPage - 1) * itemsPerPage;
      var end = start + itemsPerPage;
      var paginatedProducts = products.slice(start, end);

      // Build filter buttons
      var filterHtml = '<div class="wc-data-cleanup-filters">';
      filterHtml +=
        '<button class="button wc-data-filter active" data-type="all">All <span class="count">' +
        counts.all +
        "</span></button>";
      filterHtml +=
        '<button class="button wc-data-filter" data-type="sku">SKU <span class="count">' +
        counts.sku +
        "</span></button>";
      filterHtml +=
        '<button class="button wc-data-filter" data-type="title">Title <span class="count">' +
        counts.title +
        "</span></button>";
      filterHtml +=
        '<button class="button wc-data-filter" data-type="barcode">Barcode <span class="count">' +
        counts.barcode +
        "</span></button>";
      filterHtml += "</div>";

      // Build table
      var tableHtml = '<table class="widefat striped">';
      tableHtml += "<thead><tr>";
      tableHtml +=
        '<th><input type="checkbox" id="select-all-duplicates"></th>';
      tableHtml += "<th>ID</th>";
      tableHtml += "<th>Product Name</th>";
      tableHtml += "<th>SKU</th>";
      tableHtml += "<th>Product Type</th>";
      tableHtml += "<th>Duplicate Type</th>";
      tableHtml += "<th>Actions</th>";
      tableHtml += "</tr></thead>";
      tableHtml += '<tbody id="duplicate-products-list">';

      paginatedProducts.forEach(function (product) {
        tableHtml += "<tr>";
        tableHtml +=
          '<td><input type="checkbox" class="duplicate-checkbox" value="' +
          product.id +
          '"></td>';
        tableHtml +=
          "<td>" +
          product.id +
          (product.is_variation ? " <small>(Variation)</small>" : "") +
          "</td>";
        tableHtml += "<td>" + product.name + "</td>";
        tableHtml += "<td>" + (product.sku || "-") + "</td>";
        // Format product type for display
        var productType = product.product_type || "simple";
        var displayType =
          productType.charAt(0).toUpperCase() +
          productType.slice(1).replace("_", " ");
        if (product.is_variation) {
          displayType = "Variation";
        }
        tableHtml += "<td>" + displayType + "</td>";
        tableHtml +=
          '<td><span class="badge badge-' +
          product.type +
          '">' +
          product.type.toUpperCase() +
          "</span></td>";
        tableHtml += "<td>";
        tableHtml +=
          '<a href="' +
          product.edit_link +
          '" target="_blank" class="button button-small">Edit</a> ';
        tableHtml +=
          '<button class="button button-small button-link-delete delete-single" data-id="' +
          product.id +
          '">Delete</button>';
        tableHtml += "</td>";
        tableHtml += "</tr>";
      });

      tableHtml += "</tbody></table>";

      // Build pagination
      var paginationHtml = "";
      if (totalPages > 1) {
        paginationHtml = '<div class="tablenav"><div class="tablenav-pages">';
        paginationHtml +=
          '<span class="displaying-num">' + products.length + " items</span> ";
        paginationHtml += '<span class="pagination-links">';

        // First page
        if (currentPage > 1) {
          paginationHtml +=
            '<a class="first-page button" href="#" data-page="1"><span aria-hidden="true">«</span></a> ';
          paginationHtml +=
            '<a class="prev-page button" href="#" data-page="' +
            (currentPage - 1) +
            '"><span aria-hidden="true">‹</span></a> ';
        } else {
          paginationHtml += '<span class="button disabled">«</span> ';
          paginationHtml += '<span class="button disabled">‹</span> ';
        }

        paginationHtml += '<span class="paging-input">';
        paginationHtml +=
          '<span class="current-page">' + currentPage + "</span> of ";
        paginationHtml += '<span class="total-pages">' + totalPages + "</span>";
        paginationHtml += "</span> ";

        // Last page
        if (currentPage < totalPages) {
          paginationHtml +=
            '<a class="next-page button" href="#" data-page="' +
            (currentPage + 1) +
            '"><span aria-hidden="true">›</span></a> ';
          paginationHtml +=
            '<a class="last-page button" href="#" data-page="' +
            totalPages +
            '"><span aria-hidden="true">»</span></a>';
        } else {
          paginationHtml += '<span class="button disabled">›</span> ';
          paginationHtml += '<span class="button disabled">»</span>';
        }

        paginationHtml += "</span></div></div>";
      }

      // Build action buttons
      var actionsHtml = '<div class="wc-data-cleanup-bulk-actions">';
      actionsHtml +=
        '<button class="button" id="clear-results">Clear Results</button> ';
      actionsHtml +=
        '<button class="button" id="rescan-duplicates">Rescan</button> ';
      actionsHtml += '<span class="wc-data-cleanup-action-separator">|</span> ';
      actionsHtml +=
        '<button class="button button-primary" id="delete-selected">Delete Selected</button> ';
      actionsHtml +=
        '<button class="button button-link-delete" id="delete-all">Delete All</button>';
      actionsHtml += "</div>";

      // Display everything
      $(".wc-data-cleanup-results-section")
        .html(filterHtml + tableHtml + paginationHtml + actionsHtml)
        .show();

      // Bind filter events
      bindFilterEvents();
    }

    // Filter products
    function filterProducts(type) {
      currentFilter = type;
      var filtered =
        type === "all"
          ? allDuplicates
          : allDuplicates.filter((p) => p.type === type);

      // Update table rows
      var tableHtml = "";
      filtered.forEach(function (product) {
        tableHtml += "<tr>";
        tableHtml +=
          '<td><input type="checkbox" class="duplicate-checkbox" value="' +
          product.id +
          '"></td>';
        tableHtml +=
          "<td>" +
          product.id +
          (product.is_variation ? " <small>(Variation)</small>" : "") +
          "</td>";
        tableHtml += "<td>" + product.name + "</td>";
        tableHtml += "<td>" + (product.sku || "-") + "</td>";
        // Format product type for display
        var productType = product.product_type || "simple";
        var displayType =
          productType.charAt(0).toUpperCase() +
          productType.slice(1).replace("_", " ");
        if (product.is_variation) {
          displayType = "Variation";
        }
        tableHtml += "<td>" + displayType + "</td>";
        tableHtml +=
          '<td><span class="badge badge-' +
          product.type +
          '">' +
          product.type.toUpperCase() +
          "</span></td>";
        tableHtml += "<td>";
        tableHtml +=
          '<a href="' +
          product.edit_link +
          '" target="_blank" class="button button-small">Edit</a> ';
        tableHtml +=
          '<button class="button button-small button-link-delete delete-single" data-id="' +
          product.id +
          '">Delete</button>';
        tableHtml += "</td>";
        tableHtml += "</tr>";
      });

      $("#duplicate-products-list").html(tableHtml);
    }

    // Bind filter events
    function bindFilterEvents() {
      $(".wc-data-filter").on("click", function () {
        $(".wc-data-filter").removeClass("active");
        $(this).addClass("active");
        var type = $(this).data("type");
        filterProducts(type);
      });
    }

    // Scan button click
    $(".wc-data-cleanup-scan-duplicates").on("click", function () {
      scanDuplicates();
    });

    // Select all checkbox
    $(document).on("change", "#select-all-duplicates", function () {
      $(".duplicate-checkbox").prop("checked", this.checked);
    });

    // Pagination clicks
    $(document).on("click", ".pagination-links a", function (e) {
      e.preventDefault();
      var page = $(this).data("page");
      if (page) {
        var filtered =
          currentFilter === "all"
            ? allDuplicates
            : allDuplicates.filter((p) => p.type === currentFilter);
        displayResults(filtered, page);
      }
    });

    // Single delete button
    $(document).on("click", ".delete-single", function () {
      var productId = $(this).data("id");
      var confirmMsg = $(
        '<div class="notice notice-warning"><p><strong>Are you sure you want to delete this product?</strong></p><p>This action cannot be undone.</p><p><button class="button button-primary confirm-delete" data-id="' +
          productId +
          '">Yes, Delete</button> <button class="button cancel-delete">Cancel</button></p></div>'
      );
      $(this)
        .closest("tr")
        .after(
          '<tr class="delete-confirm"><td colspan="8">' +
            confirmMsg.prop("outerHTML") +
            "</td></tr>"
        );
    });

    // Confirm single delete
    $(document).on("click", ".confirm-delete", function () {
      var productId = $(this).data("id");
      deleteSingleProduct(productId);
      $(".delete-confirm").remove();
    });

    // Cancel delete
    $(document).on("click", ".cancel-delete", function () {
      $(".delete-confirm").remove();
    });

    // Delete single product function
    function deleteSingleProduct(productId) {
      showLoading();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_products",
          product_ids: [productId],
          force_delete: true,
          nonce: wc_data_cleanup_params.nonce,
        },
        success: function (response) {
          hideLoading();
          if (response.success) {
            showMessage("success", "Product deleted successfully");
            // Re-scan to update the list
            scanDuplicates();
          } else {
            showMessage("error", "Failed to delete product");
          }
        },
        error: function () {
          hideLoading();
          showMessage("error", "An error occurred while deleting the product");
        },
      });
    }

    // Delete selected
    $(document).on("click", "#delete-selected", function () {
      var selected = [];
      $(".duplicate-checkbox:checked").each(function () {
        selected.push($(this).val());
      });

      if (selected.length === 0) {
        showMessage("warning", "Please select products to delete");
        return;
      }

      // Show WordPress-style confirmation
      var confirmHtml =
        '<div class="notice notice-warning inline wc-data-cleanup-notice-spacing">';
      confirmHtml += "<p><strong>Confirm Deletion</strong></p>";
      confirmHtml +=
        "<p>You are about to permanently delete " +
        selected.length +
        " product(s). This action cannot be undone.</p>";
      confirmHtml += "<p>";
      confirmHtml +=
        '<button class="button button-primary" id="confirm-bulk-delete">Yes, Delete</button> ';
      confirmHtml +=
        '<button class="button" id="cancel-bulk-delete">Cancel</button>';
      confirmHtml += "</p></div>";

      // Insert confirmation after the button
      $("#delete-selected").after(confirmHtml);
      $("#delete-selected").hide();

      // Store selected items
      window.pendingDeleteIds = selected;
    });

    // Confirm bulk delete
    $(document).on("click", "#confirm-bulk-delete", function () {
      var selected = window.pendingDeleteIds || [];
      if (selected.length === 0) return;

      showLoading();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_products",
          product_ids: selected,
          force_delete: true,
          nonce: wc_data_cleanup_params.nonce,
        },
        success: function (response) {
          hideLoading();
          if (response.success) {
            showMessage(
              "success",
              "Successfully deleted " + selected.length + " products"
            );
            // Remove confirmation and show button again
            $(".notice-warning.inline").remove();
            $("#delete-selected").show();
            window.pendingDeleteIds = null;
            // Re-scan to update the list
            scanDuplicates();
          } else {
            showMessage("error", "Failed to delete products");
          }
        },
        error: function () {
          hideLoading();
          showMessage("error", "An error occurred while deleting products");
        },
      });
    });

    // Cancel bulk delete
    $(document).on("click", "#cancel-bulk-delete", function () {
      $(".notice-warning.inline").remove();
      $("#delete-selected").show();
      window.pendingDeleteIds = null;
    });

    // Clear results button
    $(document).on("click", "#clear-results", function () {
      $(".wc-data-cleanup-results-section").fadeOut(function () {
        $(this).html("").hide();
        allDuplicates = [];
        currentFilter = "all";
        // Show the scanner section again with animation
        $(".wc-data-cleanup-scanner-section").slideDown();
        showMessage(
          "info",
          "Results cleared. Click 'Scan for Duplicates' to scan again."
        );
      });
    });

    // Rescan button
    $(document).on("click", "#rescan-duplicates", function () {
      scanDuplicates();
    });

    // Delete All button
    $(document).on("click", "#delete-all", function () {
      if (allDuplicates.length === 0) {
        showMessage("warning", "No products to delete");
        return;
      }

      // Get all product IDs
      var allIds = allDuplicates.map(function (product) {
        return product.id;
      });

      // Show WordPress-style confirmation
      var confirmHtml =
        '<div class="notice notice-error inline wc-data-cleanup-notice-spacing">';
      confirmHtml +=
        "<p><strong>⚠️ WARNING: Delete ALL Duplicates</strong></p>";
      confirmHtml +=
        "<p>You are about to permanently delete ALL " +
        allIds.length +
        " duplicate products shown in the results. This action cannot be undone!</p>";
      confirmHtml += "<p>";
      confirmHtml +=
        '<button class="button button-primary wc-data-cleanup-delete-all-danger" id="confirm-delete-all">Yes, Delete All</button> ';
      confirmHtml +=
        '<button class="button" id="cancel-delete-all">Cancel</button>';
      confirmHtml += "</p></div>";

      // Insert confirmation after the button
      $("#delete-all").after(confirmHtml);
      $("#delete-all").hide();
      $("#delete-selected").hide();

      // Store all IDs
      window.pendingDeleteAllIds = allIds;
    });

    // Confirm delete all
    $(document).on("click", "#confirm-delete-all", function () {
      var allIds = window.pendingDeleteAllIds || [];
      if (allIds.length === 0) return;

      showLoading();

      $.ajax({
        url: wc_data_cleanup_params.ajax_url,
        type: "POST",
        data: {
          action: "wc_data_cleanup_delete_products",
          product_ids: allIds,
          force_delete: true,
          nonce: wc_data_cleanup_params.nonce,
        },
        success: function (response) {
          hideLoading();
          if (response.success) {
            showMessage(
              "success",
              "Successfully deleted all " +
                allIds.length +
                " duplicate products"
            );
            // Remove confirmation and show buttons again
            $(".notice-error.inline").remove();
            $("#delete-all").show();
            $("#delete-selected").show();
            window.pendingDeleteAllIds = null;
            // Clear results
            $(".wc-data-cleanup-results-section").fadeOut(function () {
              $(this).html("").hide();
              allDuplicates = [];
              // Show the scanner section again
              $(".wc-data-cleanup-scanner-section").slideDown();
            });
          } else {
            showMessage("error", "Failed to delete products");
            $(".notice-error.inline").remove();
            $("#delete-all").show();
            $("#delete-selected").show();
          }
        },
        error: function () {
          hideLoading();
          showMessage("error", "An error occurred while deleting products");
          $(".notice-error.inline").remove();
          $("#delete-all").show();
          $("#delete-selected").show();
        },
      });
    });

    // Cancel delete all
    $(document).on("click", "#cancel-delete-all", function () {
      $(".notice-error.inline").remove();
      $("#delete-all").show();
      $("#delete-selected").show();
      window.pendingDeleteAllIds = null;
    });
  });
})(jQuery);
