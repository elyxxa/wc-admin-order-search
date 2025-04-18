(function($) {
  var $productsReindexButtons = $('.aos-reindex-button');
  var currentPage = 1;
  var totalProductsIndexed = 0;
  var inProgress = false;
  var totalPages = 0;
  var totalProductsCount = 0;
  var startTime = 0;

  $productsReindexButtons.on('click', handleReindexButtonClick);

  $( window ).on('beforeunload', function() {
    if (inProgress===true) {
      return 'If you leave now, re-indexing will be aborted.';
    }
  });

  function handleReindexButtonClick() {
    $productsReindexButtons.attr('disabled', 'disabled');
    inProgress = true;
    currentPage = 1;
    totalProductsIndexed = 0;
    startTime = new Date().getTime();

    // Create a progress container if it doesn't exist
    if (!$('#indexing-progress').length) {
      $('<div id="indexing-progress" style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;"><div class="progress-bar" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.5s;"></div><div class="progress-text" style="margin-top: 10px;"></div></div>')
        .insertAfter($productsReindexButtons.parent());
    }

    updateIndexingPercentage(0);
    updateProgressText('Starting indexing process...');

    reIndex($productsReindexButtons.data('nonce'));
  }

  function updateIndexingPercentage(amount) {
    $('#indexing-progress .progress-bar').css('width', amount + '%');
  }

  function updateProgressText(text) {
    $('#indexing-progress .progress-text').html(text);
  }

  function formatTime(ms) {
    var seconds = Math.floor(ms / 1000);
    var minutes = Math.floor(seconds / 60);
    seconds = seconds % 60;
    return minutes + 'm ' + seconds + 's';
  }

  function reIndex(nonce) {
    var data = {
      'action': 'wc_psa_reindex',
      'page': currentPage,
      '_wpnonce': nonce
    };

    $.post(ajaxurl, data, function(response) {
      if(typeof response.success !== 'undefined' && response.success === false) {
        alert('An error occurred: '+ response.data.message);
        resetButtons();
        return;
      }

      if(typeof response.recordsPushedCount === 'undefined') {
        alert('You should first configure your Algolia account settings.');
        resetButtons();
        return;
      }

      totalProductsIndexed += response.recordsPushedCount;
      totalPages = response.totalPagesCount;
      totalProductsCount = response.totalProductsCount || totalProductsCount;

      var progress = Math.round((currentPage / totalPages) * 100);
      updateIndexingPercentage(progress);

      var elapsedTime = (new Date().getTime() - startTime);
      var itemsPerSecond = totalProductsIndexed / (elapsedTime / 1000);
      var estimatedTotalTime = totalProductsCount / itemsPerSecond * 1000;
      var estimatedRemaining = estimatedTotalTime - elapsedTime;

      var progressText = 'Indexed ' + totalProductsIndexed + ' of ' + totalProductsCount + ' products (' + progress + '%)';
      progressText += '<br>Page ' + currentPage + ' of ' + totalPages;
      progressText += '<br>Time elapsed: ' + formatTime(elapsedTime);

      if (estimatedRemaining > 0 && !isNaN(estimatedRemaining)) {
        progressText += '<br>Estimated time remaining: ' + formatTime(estimatedRemaining);
        progressText += '<br>Processing speed: ' + Math.round(itemsPerSecond) + ' products/second';
      }

      updateProgressText(progressText);

      if(currentPage < totalPages) {
        currentPage++;
        setTimeout(function() {
          reIndex(nonce);
        }, 100); // Add a small delay between requests
      } else {
        handleReIndexFinish();
      }
    }).fail(function(response) {
      alert('An error occurred. Please try again.');
      resetButtons();
    });
  }

  function handleReIndexFinish() {
    var totalTime = (new Date().getTime() - startTime);
    var message = 'Successfully indexed ' + totalProductsIndexed + ' products!';
    message += '\nTotal time: ' + formatTime(totalTime);

    alert(message);
    updateProgressText('Completed! Indexed ' + totalProductsIndexed + ' products in ' + formatTime(totalTime));
    resetButtons();
  }

  function resetButtons() {
    currentPage = 1;
    inProgress = false;
    $productsReindexButtons.text('Re-index products');
    $productsReindexButtons.removeAttr('disabled');
  }

})(jQuery);


