<html>
<body>
	<?php
	$valid = 1;
	$fileId = '';
	
	if( isset($_GET["state"]) && $_GET["state"] )
	{
		$state = json_decode($_GET["state"]);
		
		if( $state->action == 'open' )
		{
			if( $state->ids )
				$fileId = $state->ids[0];
			else
			{
				echo 'A document created in Drive does not support direct download. You should first convert it to a downloadable format';
				$valid = 0;
			}
		}
		else if( $state->action == 'create' )
		{
			$fileId = $state->folderId;
		}
		else
		{
			echo 'Invalid state';
			$valid = 0;
		}
	}
	else
	{
		echo 'Use the service with \'Open\' or \'Create\' buttons in Drive UI';
		$valid = 0;
	}?>
	
	<input id="fileId" type="hidden" value="<?php echo $fileId; ?>" />
	
	<pre>Source code available at: <a href="https://github.com/yasirkula/DownloadLinkGeneratorForGoogleDrive">https://github.com/yasirkula/DownloadLinkGeneratorForGoogleDrive</a> (using <i>HTML</i>, <i>PHP</i> and <i>Javascript</i>)</br>
Have any questions? Drop me a mail at <a href="mailto:yasirkula@gmail.com">yasirkula@gmail.com</a></pre>
	
	<div id="authorize-div" style="display: none">
		You need to authorize access to Drive first: <button id="authorize-button" onclick="handleAuthClick()">Authorize</button>
	</div>
	
	<?php if( $valid == 1 ) { ?>
	<pre id="status"></pre>
	<pre id="result"></pre>
	<pre id="error" style="color: red; text-style: bold;"></pre>
	<?php } ?>
	
	<script type="text/javascript">
	var CLIENT_ID = 'YOUR_APP_CLIENT_ID';
	var SCOPES = 'https://www.googleapis.com/auth/drive.install https://www.googleapis.com/auth/drive.metadata.readonly';

	var statusText = document.getElementById('status');
	var resultText = document.getElementById('result');
	var errorText = document.getElementById('error');
	
	var waitingFoldersStack = [];
	
	function checkAuth() 
	{
		if( isValidOp() )
		{
			gapi.auth.authorize( {
				'client_id': CLIENT_ID,
				'scope': SCOPES,
				'immediate': true
			}, handleAuthResult );
		}
	}

	function handleAuthResult( authResult ) 
	{
		var authorizeDiv = document.getElementById('authorize-div');
		if (authResult && !authResult.error) {
			authorizeDiv.style.display = 'none';
			loadDriveApi();
		} else {
			authorizeDiv.style.display = 'inline';
		}
	}

	function handleAuthClick() 
	{
		if( isValidOp() )
		{
			gapi.auth.authorize( {
				client_id: CLIENT_ID,
				scope: SCOPES,
				immediate: false
			}, handleAuthResult );
		}
		
		return false;
	}

	function loadDriveApi() 
	{
		gapi.client.load('drive', 'v3', handleRequest);
	}

	function handleRequest() 
	{
		statusText.innerHTML = "Status: <span style=\"text-style=bold; color:blue;\">please wait...</span>";
		
		var request = gapi.client.drive.files.get({
			'fileId': getFileId(),
			'fields': "mimeType, webContentLink"
		});
		
		request.execute( function(resp) {
			if( !resp.error )
			{
				if( resp.mimeType == 'application/vnd.google-apps.folder' )
				{
					getFilesRecursively( getFileId(), "" );
				}
				else
				{
					if( resp.webContentLink )
						resultText.innerHTML = resp.webContentLink;
					else
						resultText.innerHTML = "File is not shared";
						
					statusText.innerHTML = "Status: <span style=\"text-style=bold; color:green;\">finished</span>";
				}
			}
			else
				handleError( resp.error );
		});
	}
	
	function getFilesRecursively( folderId, relativePath )
	{
		var getFiles = function(request) 
		{
			request.execute(function(resp) 
			{
				if( !resp.error )
				{
					var files = resp.files;
					if (files && files.length > 0) 
					{
						for (var i = 0; i < files.length; i++) 
						{
							var file = files[i];
							if( file.webContentLink )
								resultText.innerHTML += relativePath + file.name + " " + file.webContentLink + "\r\n";
						}
					}
					
					var nextPageToken = resp.nextPageToken;
					if (nextPageToken) 
					{
						request = gapi.client.drive.files.list({
							'q': "trashed=false and '" + folderId + "' in parents and mimeType != 'application/vnd.google-apps.folder'",
							'pageSize': 1000,
							'fields': "nextPageToken, files(name, webContentLink)",
							'pageToken': nextPageToken
						});
						
						getFiles(request);
					}
					else
					{
						if( waitingFoldersStack.length > 0 )
						{
							var folderToEnter = waitingFoldersStack.shift();
							console.log( "Entering folder: " + folderToEnter._relativePath );
							getFilesRecursively( folderToEnter._id, folderToEnter._relativePath );
						}
						else
						{
							statusText.innerHTML = "Status: <span style=\"text-style=bold; color:green;\">finished</span>";
						}
					}
				}
				else
					handleError( resp.error );
			});
		}
		
		var getFolders = function(request) 
		{
			request.execute(function(resp) 
			{
				if( !resp.error )
				{
					var folders = resp.files;
					if (folders && folders.length > 0) 
					{
						for (var i = 0; i < folders.length; i++) 
						{
							var folder = folders[i];
							waitingFoldersStack.push( { _id: folder.id, _relativePath: relativePath + folder.name + "\\" } )
						}
					}
					
					var nextPageToken = resp.nextPageToken;
					if (nextPageToken) 
					{
						request = gapi.client.drive.files.list({
							'q': "trashed=false and '" + folderId + "' in parents and mimeType = 'application/vnd.google-apps.folder'",
							'pageSize': 1000,
							'fields': "nextPageToken, files(id, name)",
							'pageToken': nextPageToken
						});
						
						getFolders(request);
					}
					else
					{
						request = gapi.client.drive.files.list({
							'q': "trashed=false and '" + folderId + "' in parents and mimeType != 'application/vnd.google-apps.folder'",
							'pageSize': 1000,
							'fields': "nextPageToken, files(name, webContentLink)"
						});
						
						getFiles(request);
					}
				}
				else
					handleError( resp.error );
			});
		}
		
		var request = gapi.client.drive.files.list({
			'q': "trashed=false and '" + folderId + "' in parents and mimeType = 'application/vnd.google-apps.folder'",
			'pageSize': 1000,
			'fields': "nextPageToken, files(id, name)"
		});
		
		getFolders( request );
	}
	
	function getFileId()
	{
		var val = document.getElementById('fileId').value;
		if( !val )
			return "";

		return val;
	}
	
	function isValidOp()
	{
		return getFileId().length > 0;
	}
	
	function handleError( err )
	{
		console.log( "ERROR: " + JSON.stringify( err ) );
		
		var reason = "";
		var msg = "";
		
		if( err.errors && err.errors.length > 0 )
		{
			reason = err.errors[0].reason;
			msg = err.errors[0].message;
		}
		else if( err.data && err.data.length > 0 )
		{
			reason = err.data[0].reason;
			msg = err.data[0].message;
		}
		
		if( err.code == 401 )
		{
			handleAuthClick();
		}
		else if( err.code == 403 )
		{
			if( reason == "rateLimitExceeded" || reason == "userRateLimitExceeded" )
				errorText.innerHTML = "Too many requests; try again in a minute.";
			else if( reason == "dailyLimitExceeded" )
				errorText.innerHTML = "App reached daily limit (just wow O_O ); service will be available tomorrow.";
			else
				errorText.innerHTML = err.code + ": " + err.message + "(" + reason + ": " + msg + ")\r\n";
		}
		else
		{
			errorText.innerHTML = err.code + ": " + err.message + " (" + reason + ": " + msg + ")\r\n";
		}
		
		statusText.innerHTML = "Status: <span style=\"text-style=bold; color:red;\">see error log below</span>";
	}

	function appendPre(message) 
	{
		var pre = document.getElementById('output');
		var textContent = document.createTextNode(message + '\n');
		pre.appendChild(textContent);
	}
	</script>
	<script src="https://apis.google.com/js/client.js?onload=checkAuth"></script>
</body>
</html>