self.pollInterval = 2000;
self.pollUrl = "/mpdstatus";
self.addEventListener("message", function(e) {
	var data = e.data;
	switch (data.cmd) {
		case "start":
			self.poll();
			break;
		case "setMiliseconds":
			if(data.value < 2000) {
				self.postMessage("Invalid bvalue for miliseconds: " + data.value + "! terminating worker...");
				self.close();
				break;
			}
			self.pollInterval = data.value;
			break;
		case "setPollUrl":
			self.pollUrl = data.value;
			break;
		case "refreshInterval":
			clearTimeout(self.poller);
			self.poll();
			break;
		case "refreshIntervalDelayed":
			clearTimeout(self.poller);
			setTimeout(function(){self.poll("?force=1");},200);
			break;
		case "stop":
			self.close();
			break;
	}
}, false);

self.poll = function(queryString) {
	var ajax = new XMLHttpRequest();
	//console.log("pollworker.pollUrl", self.pollUrl);
	ajax.open("GET", self.pollUrl + ((queryString)?queryString:""), true);
	ajax.onreadystatechange = function(){
		if(this.readyState === XMLHttpRequest.DONE){
			if(this.status === 200){
				try {
					self.postMessage(JSON.parse(this.responseText));
					return;
				} catch(e) {
					self.postMessage("Poll-response is not parsable as JSON! terminating worker...");
					self.close();
					return;
				}
			}
			self.postMessage("Response status is not 200 but " + this.statusText + "! terminating worker...");
			self.close();
		}
	};
	ajax.send(null);
	self.poller = setTimeout(
		self.poll,
		self.pollInterval
	);
};
