/*global jQuery*/
(function($){
	window.TwitterResponse = function(data) {
		if(data.handle) {
			$('#ConnectTwitterButton').replaceWith('Connected to Twitter account ' + data.handle + '. <a href="' + data.removeLink + '" id="RemoveTwitterButton">Disconnect</a>');
		}
	};
	$('#ConnectTwitterButton').livequery('click', function (e) {
		window.open('TwitterCallback/TwitterConnect').focus();
		e.stopPropagation();
		return false;
	});
	$('#RemoveTwitterButton').livequery('click', function (e) {
		$.get($(this).attr('href'));
		$(this).parent().html('<img src="twitter/Images/connect.png" id="ConnectTwitterButton" alt="Connect to Twitter" />');
		e.stopPropagation();
		return false;
	});
}(jQuery));
