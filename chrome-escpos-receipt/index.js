var $ = function(id) { return document.getElementById(id); };
selectedPrinters = [];

function selectPrinter() {
  // Dialog and printer selection
  var onDeviceFound = function(devices) {
    $('printerList').innerHTML = '';
    if(devices.length == 0) {
      updateStatus('No devices selected');
      return;
    }
    // Refresh the printer list
    $('printerList').innerHTML = '<tr><th>Manufacturer</th><th>Product name</th></tr>';
    selectedPrinters = [];
    devices.forEach(function(device) {
      // Add each printer to list
      idStr = device.device
      $('printerList').innerHTML += '<tr><td id=\'' + idStr + '-manufacturerName\'></td><td><td id=\'' + idStr + '-productName\'></td></tr>';
      $(idStr + '-manufacturerName').innerText = device.manufacturerName;
      $(idStr + '-productName').innerText = device.productName;
      selectedPrinters.push(device);
    });
    updateStatus(selectedPrinters.length + ' printer(s) selected');
  }
  // Allow the user to select any devices listed in the manifest
  filters = chrome.runtime.getManifest().optional_permissions[0].usbDevices;
  chrome.usb.getUserSelectedDevices({'multiple': true, 'filters': filters}, onDeviceFound)
}

function print() {
  // Print text and cut paper. This data contains 'Hello world LF GS V 65 3'
  hello = [0x48, 0x65, 0x6c, 0x6c, 0x6f, 0x20, 0x77, 0x6f, 0x72, 0x6c, 0x64, 0x0a, 0x1d, 0x56, 0x41, 0x03];
  var totalDataSize = hello.length;
  var data = new ArrayBuffer(totalDataSize);
  var dataView = new Uint8Array(data, 0, totalDataSize);
  dataView.set(hello, 0)
  // Print this data to all devices
  printDataToDevices(data, selectedPrinters)
}

function printDataToDevices(data, devices) {
  // For each device, print data to the device
  if(devices.length == 0) {
    updateStatus("No printers selected.");
    return;
  }
  devices.forEach(function(device) {
    printDataToDevice(data, device);
  });
}

function printDataToDevice(data, device) {
  // Open a device and print the data to the resulting handle
  chrome.usb.openDevice(device, function(handle) {
    printDataToHandle(data, device, handle)
  });
}

function printDataToHandle(data, device, handle) {
  // Claim interface and print data to it
  chrome.usb.claimInterface(handle, 0, function() {
    if (chrome.runtime.lastError) {
      console.error(chrome.runtime.lastError, device);
      updateStatus("Failed to claim interface for device");
      return;
    }
    printDataToInterface(data, device, handle);
  });
}

function printDataToInterface(data, device, handle) {
  // Transfer data to a claimed interface on an open device
  var info = {
    "direction": "out",
    "endpoint": 1,
    "data": data
  };
  chrome.usb.bulkTransfer(handle, info, function(transferResult) {
    chrome.usb.releaseInterface(handle, 0, function() {
    if (chrome.runtime.lastError)
      console.error(chrome.runtime.lastError);
      return;
    });
  });
}

function updateStatus(text) {
  // Change status line on page
  $('status').innerText = 'Status: ' + text;
}

window.addEventListener('DOMContentLoaded', function() {
  // Hook up buttons
  $('selectPrinter').addEventListener('click', selectPrinter);
  $('print').addEventListener('click', print);
});
