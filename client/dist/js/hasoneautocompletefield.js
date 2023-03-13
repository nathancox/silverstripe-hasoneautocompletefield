(function($) {
    $.entwine("ss", function($) {
        // cache previous search results
        var searchCache = {};

        // Clicking the edit button "opens" the search field
        $(".hasoneautocomplete-editbutton").entwine({
            onclick: function() {
                this.closest('.hasoneautocomplete').find('.hasoneautocomplete-search').trigger('open');
            }
        });

        // Clicking the cancel button "closes" the search field
        $(".hasoneautocomplete-cancelbutton").entwine({
            onclick: function() {
                this.closest('.hasoneautocomplete').find('.hasoneautocomplete-search').trigger('close');
            }
        });

        $(".hasoneautocomplete-clearbutton").entwine({
            onclick: function() {
                var defaultText = String(this.closest('.hasoneautocomplete').data('default-text')).trim();
                var $currentText = this.closest('.hasoneautocomplete').find('.hasoneautocomplete-currenttext').first();
                var $idField = this.closest('.hasoneautocomplete').find('.hasoneautocomplete-id').first();
                if ($currentText.length && $idField.length) {
                    if (defaultText === '') {
                        defaultText = '(none)';
                    }

                    $currentText.html(defaultText);
                    $idField.val(0).change();
                }
			},
			onmatch: function() {
                $idField = this.closest('.hasoneautocomplete').find('.hasoneautocomplete-id').first();
                if ($idField.length) {
                    $idField.change(function(event) {
                        var $clearButton = $(this).closest('.hasoneautocomplete').find('.hasoneautocomplete-clearbutton').first();
                        if ($clearButton.length) {
                            var newValue = String($(this).val()).trim();
                            if (newValue === '' || newValue === '0') {
                                $clearButton.hide();
                            } else {
                                $clearButton.show();
                            }
                        }
                    });
                }
			}
		});

        $(".hasoneautocomplete-search").entwine({
            onmatch: function (event) {
                var t = this;

                var autocompleteOptions = {
                    source: function(request, response){
                        var searchField = $(this.element);
                        var form = $(this.element).closest("form");
                        var searchTerm = searchField.val();

                        if (searchTerm in searchCache) {
                            response(searchCache[searchTerm]);
                            return;
                        }

                        $.ajax({
                            headers: {
                                "X-Pjax" : 'Partial'
                            },
                            type: "GET",
                            url: $(searchField).data('search-url'),
                            data: 'query='+encodeURIComponent(searchTerm),
                            success: function(data) {

                                var processedData = $.map(JSON.parse(data), function(item, id) {
                                    var output = {
                                        label: item.name, // what's shown in the dropdown
                                        value: '',  // what's shown in the text field
                                        id: item.id     // what's saved to the real field
                                    };

                                    if (item.currentString) {
                                        output.currentString = item.currentString; // what's shown next to the text field
                                    }

                                    return output;
                                });
                                searchCache[searchTerm] = processedData;

                                response(processedData);
                            },
                            error: function(e) {
                                console.log(e);
                                alert('An error occured while fetching data from the server\n Please try again later.');
                            }
                        });
                    },
                    select: function(event, ui) {
                        // update the display string
                        if (ui.item.currentString) {
                            t.getCurrentTextElement().html(ui.item.currentString);
                        }

                        // trigger the form's change detection if needed
                        var oldID = t.getIDElement().val();
                        if (oldID != ui.item.id) {
                            t.getIDElement().val(ui.item.id).change();
                        }

                        t.trigger('close');

                    }

                };

                var autocompleteDelay = this.getFieldElement().data('autocomplete-delay');
                if (autocompleteDelay === undefined || isNaN(autocompleteDelay)) {
                    autocompleteDelay = 300;
                }

                this.autocomplete($.extend(autocompleteOptions, {
                    delay: autocompleteDelay
                })).data('autocomplete')._renderItem = function(ul, item) {
                    var output = $("<li>")
                        .append($("<a>").html(item.label));
                    return  output.appendTo(ul);
                };
            },

            // When the search field is "opened", add the class that toggles visibility and
            // set the focus on the field
            onopen: function() {
                this.getFieldElement().addClass('showsearch');
                this.trigger('focus');
            },

            // When the search field is "closed" hide it and empty it's current value
            onclose: function() {
                this.val('');
                // this enables you to search for the same thing twice
                this.data().uiAutocomplete.term = null;
                this.getFieldElement().removeClass('showsearch');
            },


            getCurrentTextElement: function() {
                return this.closest('.hasoneautocomplete').find('.hasoneautocomplete-currenttext').first();
            },

            getIDElement: function() {
                return this.closest('.hasoneautocomplete').find('.hasoneautocomplete-id').first();
            },

            getFieldElement: function() {
                return this.closest('.hasoneautocomplete');
            }

        });

    });
})(jQuery);
