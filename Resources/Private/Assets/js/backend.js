(function ($, TYPO3, MSH) {
  MSH = MSH || {};
  MSH = {
    numberOfSeoChecks: 6,
    googleTitleLengthPixel: 580,
    googleDescriptionLengthPixel: 920,
    googleDescriptionMinLengthPixel: 300,
    googleDescriptionFontSize: '13px',
    googleTitleFontSize: '18px',
    googleFontFamily: 'arial,sans-serif',
    googleEllipsis: ' ...',
    $previewContainers: {},
    canvasRenderingContext: {},
    editing: true,
    init: function () {
      var that = this;

      this.canvasRenderingContext = document.createElement('canvas').getContext('2d');
      this.$previewContainers = $('.google-preview');

      // Initial description rendering (kills whitespace etc.)
      this.$previewContainers.each(function () {
        that.editing = 0 < parseInt($(this).attr('data-editing'));

        that.renderPreviewDescription($(this));

        if (that.editing) {
          that.checkFocusKeyword($(this), $(this).find('.focus-keyword input').val());
        } else {
          that.checkFocusKeyword($(this), $('#focusKeyword').val());
        }

        if (that.editing) {
          $(this).parents('.page').find('.seo-check .all').html(that.numberOfSeoChecks);

          that.updatePreviewAlerts($(this));
          that.updatePreviewEditPanelProgressBar($(this), 'title', that.googleTitleLengthPixel);
          that.updatePreviewEditPanelProgressBar($(this), 'description', that.googleDescriptionLengthPixel);
        } else {
          $('form').find('.seo-check .all').html(that.numberOfSeoChecks);
          that.updatePreviewAlerts($(this), $('#focusKeyword'), $('textarea[name="data[pages][' + $(this).find('input[name="pageUid"]').val() + '][description]"], textarea[name="data[pages_language_overlay][' + $(this).find('input[name="pageUid"]').val() + '][description]"]'));
        }
      });

      this.registerEvents();
    },
    registerEvents: function () {
      var that = this;

      // Change selection of pagetree depth
      $('#depthselect').on('change', function () {
        $('#depthselect-form').submit()
      });

      // Save configuration form
      $('.mindshape-seo-savebutton').on('click', function (e) {
        e.preventDefault();

        $('#mindshape-seo-configuration').submit();
      });

      // Configuration form upload fields delete function
      $('.mindshape-seo-upload').on('click', '.mindshape-seo-delete', function (e) {
        e.preventDefault();

        var $uploadContainer = $(this).parent('.mindshape-seo-upload');

        $uploadContainer.find('input[type="hidden"]').remove();
        $uploadContainer.find('.image').remove();
      });

      // Edit click on google preview
      this.$previewContainers.on('click', '.edit', function (e) {
        e.preventDefault();

        var $currentPreview = $(this).parents('.google-preview');
        var $currentEditPanel = $currentPreview.find('.edit-panel');
        var $focusKeywordMetadataPreview = $('.focus-keyword-container .focus-keyword');

        if ($currentEditPanel.is(':hidden')) {
          that.openPreviewEditPanel($currentPreview);
        } else {
          that.closePreviewEditPanel($currentPreview);
          that.restorePreviewOriginalData($currentPreview);
          that.checkAndChangeIndexPreview($currentPreview);

          var focuskeyword = $currentPreview.find('.focus-keyword input').val();

          that.checkPreviewEditPanelSaveState($currentPreview);
          that.updatePreviewEditPanelProgressBar($currentPreview, 'title', that.googleTitleLengthPixel);
          that.updatePreviewEditPanelProgressBar($currentPreview, 'description', that.googleDescriptionLengthPixel);

          if (0 < focuskeyword.trim().length) {
            that.checkFocusKeyword($currentPreview, focuskeyword);

          }

          if ('' === focusKeyword) {
            $focusKeywordMetadataPreview.html('n/a');
            $focusKeywordMetadataPreview.addClass('focus-keyword-na');
          } else {
            $focusKeywordMetadataPreview.removeClass('focus-keyword-na');
            $focusKeywordMetadataPreview.html(focusKeyword)
          }

          that.updatePreviewAlerts($currentPreview);
        }
      });

      // Save click on edit panel
      this.$previewContainers.on('click', '.save', function (e) {
        e.preventDefault();

        that.savePreviewEditPanel($(this).parents('.google-preview'));
      });

      // Show SEO alerts
      this.$previewContainers.on('click', '.alerts', function (e) {
        e.preventDefault();

        var $alertsContainer = $(this).parents('.google-preview').find('.alerts-container');

        if ($alertsContainer.is(':hidden')) {
          $alertsContainer.slideDown();
        } else {
          $alertsContainer.slideUp();
        }
      });

      this.$previewContainers.on('click', 'input[type="checkbox"]', function () {
        var $currentPreview = $(this).parents('.google-preview');

        that.checkAndChangeIndexPreview($currentPreview);
        that.checkPreviewEditPanelSaveState($currentPreview);
      });

      // Change preview title when editing title
      this.$previewContainers.on('keyup', '.edit-panel .title', function () {
        var $currentPreview = $(this).parents('.google-preview');
        var focusKeyword = $currentPreview.find('.focus-keyword input').val().trim();

        $currentPreview.find('.preview-box .title').html(that.escapeHtml($(this).val()));
        that.updatePreviewEditPanelProgressBar($currentPreview, 'title', that.googleTitleLengthPixel);
        that.updatePreviewAlerts($currentPreview);

        if (0 < focusKeyword.length) {
          that.checkFocusKeyword($currentPreview, focusKeyword);
        }

        if (that.editing) {
          that.checkPreviewEditPanelSaveState($currentPreview);
        }
      });

      // Change preview description when editing description
      this.$previewContainers.on('keyup', '.edit-panel .description', function () {
        var $currentPreview = $(this).parents('.google-preview');
        var focusKeyword = $currentPreview.find('.focus-keyword input').val().trim();

        $currentPreview.find('.preview-box .description').html(that.escapeHtml($(this).val()));
        that.renderPreviewDescription($currentPreview);
        that.updatePreviewEditPanelProgressBar($currentPreview, 'description', that.googleDescriptionLengthPixel);

        if (0 < focusKeyword.length) {
          that.checkFocusKeyword($currentPreview, focusKeyword);
        }

        that.updatePreviewAlerts($currentPreview);

        if (that.editing) {
          that.checkPreviewEditPanelSaveState($currentPreview);
        }
      });

      if (!this.editing) {
        var $tcaForm = $('form');
        var $currentPreview = $('.google-preview');
        var currentPageUid = $currentPreview.find('input[name="pageUid"]').val();
        var focusKeyword = $('#focusKeyword').val().trim();

        $tcaForm.find('input[data-formengine-input-name="data[pages][' + currentPageUid + '][title]"], input[data-formengine-input-name="data[pages_language_overlay][' + currentPageUid + '][title]"]').on('keyup', function () {
          $currentPreview.find('.preview-box .title').html($(this).val());

          if (0 < focusKeyword.length) {
            that.checkFocusKeyword($currentPreview, focusKeyword);
          }

          that.updatePreviewAlerts($currentPreview);
        });

        $tcaForm.find('textarea[name="data[pages][' + currentPageUid + '][description]"], textarea[name="data[pages_language_overlay][' + currentPageUid + '][description]"]').on('keyup', function () {
          $currentPreview.find('.preview-box .description').html($(this).val());
          that.renderPreviewDescription($currentPreview);

          if (0 < focusKeyword.length) {
            that.checkFocusKeyword($currentPreview, focusKeyword);
          }

          that.updatePreviewAlerts($currentPreview, $('#focusKeyword'), $(this));
        });
      }

      // Update focus keyword check
      $(document).on('keyup', '.focus-keyword input, #focusKeyword', function () {
        var $currentPreview = {};

        if (that.editing) {
          $currentPreview = $(this).parents('.google-preview');
        } else {
          $currentPreview = $(document).find('.google-preview');
        }

        var focusKeyword = $(this).val().trim();

        that.checkFocusKeyword($currentPreview, focusKeyword);

        if (that.editing) {
          var $focusKeywordMetadataPreview = $currentPreview.parents('.page').find('.focus-keyword-container .focus-keyword');

          if ('' === focusKeyword) {
            $focusKeywordMetadataPreview.html('n/a');
            $focusKeywordMetadataPreview.addClass('focus-keyword-na');
          } else {
            $focusKeywordMetadataPreview.removeClass('focus-keyword-na');
            $focusKeywordMetadataPreview.html(focusKeyword)
          }

          that.updatePreviewAlerts($currentPreview);
          that.checkPreviewEditPanelSaveState($currentPreview);
        } else {
          that.updatePreviewAlerts(
            $currentPreview,
            $('#focusKeyword'),
            $('textarea[name="data[pages][' + $currentPreview.find('input[name="pageUid"]').val() + '][description]"], textarea[name="data[pages_language_overlay][' + $currentPreview.find('input[name="pageUid"]').val() + '][description]"]')
          );
        }
      });

      $(document).on('click', '.info, .progress-seo-check', function (e) {
        e.preventDefault();

        var $parent = {};

        if (that.editing) {
          $parent = $(this).parents('.page');
        } else {
          $parent = $('form');
        }

        var $modal = $('#msh-modal');

        $modal.find('.modal-body').html($parent.find('.google-preview .alerts-container').html());
        $modal.modal();
      });
    },
    restorePreviewOriginalData: function ($previewContainer) {
      $previewContainer.find('.preview-box .title').html($previewContainer.attr('data-original-title'));
      $previewContainer.find('.preview-box .description').html($previewContainer.attr('data-original-description'));
      $previewContainer.find('.edit-panel .title').val($previewContainer.attr('data-original-title'));
      $previewContainer.find('.edit-panel .description').val($previewContainer.attr('data-original-description'));
      this.renderPreviewDescription($previewContainer);
      $previewContainer.find('.edit-panel .noindex input[type="checkbox"]')
        .prop('checked', 0 < parseInt($previewContainer.attr('data-original-noindex')));
      $previewContainer.find('.edit-panel .nofollow input[type="checkbox"]')
        .prop('checked', 0 < parseInt($previewContainer.attr('data-original-nofollow')));
      $previewContainer.find('.edit-panel .focus-keyword input').val($previewContainer.attr('data-original-focuskeyword'));

      if (this.editing) {
        $previewContainer.parents('.page')
          .find('.focus-keyword-container .focus-keyword')
          .html($previewContainer.attr('data-original-focuskeyword'));
      }
    },
    renderPreviewDescription: function ($previewContainer) {
      var description = this.escapeHtml($previewContainer.find('.preview-box .description').text().trim());

      if (this.googleDescriptionLengthPixel < this.calcStringPixelLength(description, this.googleFontFamily, this.googleDescriptionFontSize)) {
        var invalidLastChar = function (description) {
          return description.slice(-1).match(/(\s|\.)/);
        };

        while (this.googleDescriptionLengthPixel < this.calcStringPixelLength(description, this.googleFontFamily, this.googleDescriptionFontSize)) {
          description = description.split(' ');

          if (description.length > 1) {
            description.pop();
            description = description.join(' ');
          } else {
            description = description.join(' ');
            description = description.slice(0, -1);
          }
        }

        while (invalidLastChar(description)) {
          description = description.slice(0, -1);
        }

        description += this.googleEllipsis;
      }

      $previewContainer.find('.preview-box .description').html(description);
    },
    checkPreviewEditPanelSaveState: function ($previewContainer) {
      var title = $previewContainer.find('.edit-panel .title').val();
      var description = $previewContainer.find('.edit-panel .description').val();
      var focusKeyword = $previewContainer.find('.focus-keyword input').val();
      var noindex = $previewContainer.find('.noindex input[type="checkbox"]').is(':checked');
      var nofollow = $previewContainer.find('.nofollow input[type="checkbox"]').is(':checked');

      if (
        0 < title.length &&
        (
          $previewContainer.attr('data-original-title') !== title.trim() ||
          $previewContainer.attr('data-original-description') !== description.trim() ||
          $previewContainer.attr('data-original-focuskeyword') !== focusKeyword.trim() ||
          0 < parseInt($previewContainer.attr('data-original-noindex')) !== noindex ||
          0 < parseInt($previewContainer.attr('data-original-nofollow')) !== nofollow
        )
      ) {
        $previewContainer.find('button.save').prop('disabled', false);
        $previewContainer.find('.edit-panel .title-container').removeClass('has-error');
      } else {
        $previewContainer.find('button.save').prop('disabled', true);

        if (0 === title.length) {
          $previewContainer.find('.edit-panel .title-container').addClass('has-error')
        }
      }
    },
    updatePreviewEditPanelProgressBar: function ($previewContainer, fieldName, maxLength) {
      var fieldText = '';

      if ('title' === fieldName) {
        fieldText = $previewContainer.find('.preview-box h3')[0].innerText;
        fieldText.replace(/\n/, ' ')
      } else {
        fieldText = $previewContainer.find('.edit-panel .description').val();
      }

      var percent = 0;
      var progressbarStatusClass = 'progress-bar-';
      var fieldLength = this.calcStringPixelLength(
        fieldText.trim(),
        this.googleFontFamily,
        fieldName === 'description' ?
          this.googleDescriptionFontSize :
          this.googleTitleFontSize
      );

      maxLength = parseInt(maxLength);

      if (fieldLength >= maxLength) {
        percent = 100;
      } else {
        percent = 100 / maxLength * fieldLength;
      }

      if (percent >= 100) {
        progressbarStatusClass += 'danger';
      } else if (percent >= 70) {
        progressbarStatusClass += 'warning';
      } else {
        progressbarStatusClass += 'success';
      }

      $previewContainer
        .find('.edit-panel .progress-' + fieldName + ' .progress-bar')
        .css('width', percent + '%')
        .removeClass('progress-bar-danger')
        .removeClass('progress-bar-warning')
        .removeClass('progress-bar-success')
        .addClass(progressbarStatusClass);
    },
    closePreviewEditPanel: function ($previewContainer) {
      $previewContainer.find('.edit-panel').slideUp();
      $previewContainer.find('button.save').show();
      $previewContainer.find('button.edit .edit-text').show();
      $previewContainer.find('button.edit .abort-text').hide();
    },
    openPreviewEditPanel: function ($previewContainer) {
      $previewContainer.find('.edit-panel').slideDown();
      $previewContainer.find('button.save').show();
      $previewContainer.find('button.edit .edit-text').hide();
      $previewContainer.find('button.edit .abort-text').show();
    },
    checkAndChangeIndexPreview: function ($previewContainer, setOriginalData) {
      var $noindexPreview = $previewContainer.parents('.page').find('.robots .noindex');
      var $nofollowPreview = $previewContainer.parents('.page').find('.robots .nofollow');

      if ($previewContainer.find('.edit-panel .noindex input[type="checkbox"]').is(':checked')) {
        if (setOriginalData) {
          $previewContainer.attr('data-original-noindex', 1);
        }

        if (this.editing) {
          $noindexPreview.html('noindex,');
          $noindexPreview.addClass('danger');
        }
      } else {
        if (setOriginalData) {
          $previewContainer.attr('data-original-noindex', 0);
        }

        if (this.editing) {
          $noindexPreview.html('index,');
          $noindexPreview.removeClass('danger');
        }
      }

      if ($previewContainer.find('.edit-panel .nofollow input[type="checkbox"]').is(':checked')) {
        if (setOriginalData) {
          $previewContainer.attr('data-original-nofollow', 1);
        }

        if (this.editing) {
          $nofollowPreview.html('nofollow');
          $nofollowPreview.addClass('danger');
        }
      } else {
        if (setOriginalData) {
          $previewContainer.attr('data-original-nofollow', 0);
        }

        if (this.editing) {
          $nofollowPreview.html('follow');
          $nofollowPreview.removeClass('danger');
        }
      }
    },
    savePreviewEditPanel: function ($previewContainer) {
      var that = this;

      if (!this.editing) {
        return;
      }

      $previewContainer.find('.edit-panel .save').prop('disabled', true);

      $.ajax({
        type: "POST",
        url: TYPO3.settings.ajaxUrls['MindshapeSeoAjaxHandler::savePage'],
        data: $previewContainer.find('form').serialize(),
        success: function () {
          $previewContainer.attr('data-original-title', $previewContainer.find('.edit-panel .title').val().trim());
          $previewContainer.attr('data-original-description', $previewContainer.find('.edit-panel .description').val().trim());
          $previewContainer.attr('data-original-focuskeyword', $previewContainer.find('.edit-panel .focus-keyword input').val().trim());

          that.checkAndChangeIndexPreview($previewContainer, true);

          that.checkPreviewEditPanelSaveState($previewContainer);
          that.closePreviewEditPanel($previewContainer);
        },
        error: function () {
          $previewContainer.find('.icon-provider-fontawesome-error').show();
        }
      });
    },
    checkFocusKeyword: function ($previewContainer, focusKeyword) {
      this.renderPreviewDescription($previewContainer);
      this.clearPreviewTitle($previewContainer);
      this.clearUrlTitle($previewContainer);

      if ('' === focusKeyword) {
        return;
      }

      var title = this.escapeHtml($previewContainer.find('.preview-box .title').text());
      var description = this.escapeHtml($previewContainer.find('.preview-box .description').text());
      var url = this.escapeHtml($previewContainer.find('.preview-box .url').text());
      var regex = new RegExp('(^|\\.|\\,|\\?|\\!|\\/|\\#|\\+|\\s)(' + this.escapeHtml(focusKeyword.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/ig, '\\$&').trim()) + ')(\\s|\\.|\\,|\\?|\\!|\\/|\\#|\\+|$)', 'igm');
      var titleMatches = title.match(regex);
      var descriptionMatches = description.match(regex);
      var urlMatches = url.match(regex);

      if (null === titleMatches) {
        $previewContainer.attr('data-keyword-title-matches', 0);
      } else {
        $previewContainer.find('.preview-box .title').html(
          title.replace(regex, '$1<span class="focus-keyword">$2</span>$3')
        );

        $previewContainer.attr('data-keyword-title-matches', titleMatches.length);
      }

      if (null === descriptionMatches) {
        $previewContainer.attr('data-keyword-description-matches', 0);
      } else {
        $previewContainer.find('.preview-box .description').html(
          description.replace(regex, '$1<span class="focus-keyword">$2</span>$3')
        );

        $previewContainer.attr('data-keyword-description-matches', descriptionMatches.length);
      }

      if (null === urlMatches) {
        $previewContainer.attr('data-keyword-url-matches', 0);
      } else {
        $previewContainer.find('.preview-box .url cite').html(
          url.replace(regex, '$1<span class="focus-keyword">$2</span>$3')
        );

        $previewContainer.attr('data-keyword-url-matches', urlMatches.length);
      }
    },
    clearPreviewTitle: function ($previewContainer) {
      $previewContainer.find('.preview-box .title').html(
        $previewContainer.find('.preview-box .title').text().trim()
      );
    },
    clearUrlTitle: function ($previewContainer) {
      $previewContainer.find('.preview-box .url cite').html(
        $previewContainer.find('.preview-box .url').text().trim()
      );
    },
    updatePreviewAlerts: function ($previewContainer, $focusKeywordInput, $descriptionInput) {
      var titleLengthPixel = this.calcStringPixelLength($previewContainer.find('.preview-box h3')[0].innerText, this.googleFontFamily, this.googleTitleFontSize);
      var description = '';

      if ('undefined' !== typeof $descriptionInput) {
        description = $descriptionInput.val();
      } else {
        description = $.trim($previewContainer.find('.edit-panel .description').val());
      }

      var descriptionLengthPixel = this.calcStringPixelLength(description, this.googleFontFamily, this.googleDescriptionFontSize);
      var focusKeyword = $.trim('undefined' !== typeof $focusKeywordInput ?
        $focusKeywordInput.val() :
        $previewContainer.find('.focus-keyword input').val());

      var $alertsContainer = $previewContainer.find('.alerts-container');
      var alertsCounter = 0;

      if (titleLengthPixel > this.googleTitleLengthPixel) {
        $alertsContainer.find('.title-length').show();
        alertsCounter++;
      } else {
        $alertsContainer.find('.title-length').hide();
      }

      if (descriptionLengthPixel > this.googleDescriptionLengthPixel) {
        $alertsContainer.find('.description-length').show();
        alertsCounter++;
      } else {
        $alertsContainer.find('.description-length').hide();
      }

      if (descriptionLengthPixel === 0) {
        $alertsContainer.find('.description-empty').show();
        $alertsContainer.find('.description-min-length').hide();
        alertsCounter++;
      } else {
        $alertsContainer.find('.description-empty').hide();

        if (descriptionLengthPixel < this.googleDescriptionMinLengthPixel) {
          $alertsContainer.find('.description-min-length').show();
          alertsCounter++;
        } else {
          $alertsContainer.find('.description-min-length').hide();
        }
      }

      if (0 === focusKeyword.length) {
        $alertsContainer.find('.focus-keyword').hide();
        $alertsContainer.find('.focus-keyword-missing').show();
        alertsCounter++;
      } else {
        $alertsContainer.find('.focus-keyword-missing').hide();
        if (0 < parseInt($previewContainer.attr('data-keyword-title-matches'))) {
          $alertsContainer.find('.focus-keyword.missing-title').hide();
          $alertsContainer.find('.focus-keyword.found-title').show();
        } else {
          $alertsContainer.find('.focus-keyword.missing-title').show();
          $alertsContainer.find('.focus-keyword.found-title').hide();
          alertsCounter++;
        }

        if (0 < parseInt($previewContainer.attr('data-keyword-description-matches'))) {
          $alertsContainer.find('.focus-keyword.missing-description').hide();
          $alertsContainer.find('.focus-keyword.found-description').show();
        } else {
          $alertsContainer.find('.focus-keyword.missing-description').show();
          $alertsContainer.find('.focus-keyword.found-description').hide();
          alertsCounter++;
        }

        if (0 < parseInt($previewContainer.attr('data-keyword-url-matches'))) {
          $alertsContainer.find('.focus-keyword.missing-url').hide();
          $alertsContainer.find('.focus-keyword.found-url').show();
        } else {
          $alertsContainer.find('.focus-keyword.missing-url').show();
          $alertsContainer.find('.focus-keyword.found-url').hide();
          alertsCounter++;
        }
      }

      $alertsContainer.find('li').removeClass('first-visible').removeClass('last-visible');

      var $visibleElements = $previewContainer.find('.alerts-container li').filter(function () {
        return $(this).css('display') !== 'none';
      });

      $visibleElements.first().addClass('first-visible');
      $visibleElements.last().addClass('last-visible');

      var $seoCheckParent = {};

      if (this.editing) {
        $seoCheckParent = $previewContainer.parents('.page');
      } else {
        $seoCheckParent = $('form');
      }

      $seoCheckParent.find('.seo-check .alerts').html(alertsCounter);

      if (alertsCounter > 0) {
        $seoCheckParent.find('.seo-check .no-error').hide();
        $seoCheckParent.find('.seo-check .error').show();
      } else {
        $seoCheckParent.find('.seo-check .no-error').show();
        $seoCheckParent.find('.seo-check .error').hide();
      }
    },
    calcStringPixelLength: function (text, fontFamily, fontSize) {
      this.canvasRenderingContext.font = fontSize + ' ' + fontFamily;

      return parseInt(this.canvasRenderingContext.measureText(text).width);
    },
    escapeHtml: function (text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  };

  $(document).ready(function () {
    MSH.init();

    var $addJsonld = $('#addJsonld');
    var $jsonld = $('#jsonld');
    var $jsonldTypeSelect = $('.type-select');
    var $jsonldLogo = $('fieldset.logo');

    if ('undefined' !== typeof $jsonldTypeSelect) {
      if ($jsonldTypeSelect.val() === 'organization') {
        $jsonldLogo.show();
      }

      $jsonldTypeSelect.on('change', function () {
        if ($jsonldTypeSelect.val() === 'organization') {
          $jsonldLogo.slideDown();
        } else {
          $jsonldLogo.slideUp();
        }
      });
    }

    if ('undefined' !== typeof $addJsonld) {
      if ($addJsonld.prop('checked')) {
        $jsonld.show();
      }

      $addJsonld.on('click', function () {
        if ($addJsonld.prop('checked')) {
          $jsonld.slideDown();
        } else {
          $jsonld.slideUp();
        }
      });
    }
  });
})(TYPO3.jQuery || jQuery, TYPO3, MSH = null);
