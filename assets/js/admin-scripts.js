jQuery(document).ready(function ($) {
  let searchTimer;
  const searchInput = $(".hfp-search-bar");
  const tableBody = $("#hfp-product-list tbody");

  searchInput.on("keyup", function () {
    clearTimeout(searchTimer);
    const query = $(this).val();

    if (query.length < 3 && query.length > 0) return;

    // Show a sleek 2026 loading state
    tableBody.css("opacity", "0.5");

    searchTimer = setTimeout(function () {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "hfp_search_products",
          query: query,
          nonce: hfp_vars.nonce,
        },
        success: function (response) {
          tableBody.html(response).css("opacity", "1");
        },
      });
    }, 300); // 300ms debounce
  });
});
