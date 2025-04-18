(function($) {
  var $ordersReindexButtons = $('.aos-reindex-button');
  var currentPage = 1;
  var totalOrdersIndexed = 0;
  var inProgress = false;
  var totalPages = 0;
  var totalOrdersCount = 0;
  var startTime = 0;

  $ordersReindexButtons.on('click', handleReindexButtonClick);

  $( window ).on('beforeunload', function() {
    if (inProgress===true) {
      return 'If you leave now, re-indexing will be aborted.';
    }
  });

  function handleReindexButtonClick() {
    $ordersReindexButtons.attr('disabled', 'disabled');
    inProgress = true;
    currentPage = 1;
    totalOrdersIndexed = 0;
    startTime = new Date().getTime();

    // Create a progress container if it doesn't exist
    if (!$('#indexing-progress').length) {
      $('<div id="indexing-progress" style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;"><div class="progress-bar" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.5s;"></div><div class="progress-text" style="margin-top: 10px;"></div></div>')
        .insertAfter($ordersReindexButtons.parent());
    }

    updateIndexingPercentage(0);
    updateProgressText('Starting indexing process...');

    reIndex($ordersReindexButtons.data('nonce'));
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
      'action': 'wc_osa_reindex',
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

      totalOrdersIndexed += response.recordsPushedCount;
      totalPages = response.totalPagesCount;
      totalOrdersCount = response.totalOrdersCount || totalOrdersCount;

      var progress = Math.round((currentPage / totalPages) * 100);
      updateIndexingPercentage(progress);

      var elapsedTime = (new Date().getTime() - startTime);
      var itemsPerSecond = totalOrdersIndexed / (elapsedTime / 1000);
      var estimatedTotalTime = totalOrdersCount / itemsPerSecond * 1000;
      var estimatedRemaining = estimatedTotalTime - elapsedTime;

      var progressText = 'Indexed ' + totalOrdersIndexed + ' of ' + totalOrdersCount + ' orders (' + progress + '%)';
      progressText += '<br>Page ' + currentPage + ' of ' + totalPages;
      progressText += '<br>Time elapsed: ' + formatTime(elapsedTime);

      if (estimatedRemaining > 0 && !isNaN(estimatedRemaining)) {
        progressText += '<br>Estimated time remaining: ' + formatTime(estimatedRemaining);
        progressText += '<br>Processing speed: ' + Math.round(itemsPerSecond) + ' orders/second';
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
    var message = 'Successfully indexed ' + totalOrdersIndexed + ' orders!';
    message += '\nTotal time: ' + formatTime(totalTime);

    alert(message);
    updateProgressText('Completed! Indexed ' + totalOrdersIndexed + ' orders in ' + formatTime(totalTime));
    resetButtons();
  }

  function resetButtons() {
    currentPage = 1;
    inProgress = false;
    $ordersReindexButtons.text('Re-index orders');
    $ordersReindexButtons.removeAttr('disabled');
  }

})(jQuery);


