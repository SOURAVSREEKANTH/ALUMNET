(() => {
    "use strict";

    const passElement = document.querySelector("[data-pass-code]");
    if (passElement && typeof QRCode === "function") {
        const passCode = passElement.dataset.passCode;
        new QRCode(passElement, {
            text: passCode,
            width: 240,
            height: 240,
            colorDark: "#17191a",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    const scannerButton = document.querySelector("[data-scanner-start]");
    const video = document.querySelector("[data-scanner-video]");
    const message = document.querySelector("[data-scanner-message]");
    const form = document.querySelector("[data-check-in-form]");
    if (!scannerButton || !video || !message || !form) {
        return;
    }

    const passCodeInput = form.querySelector("[data-pass-code]");
    const methodInput = form.querySelector("[data-check-in-method]");
    let stream = null;
    let detector = null;
    let scanning = false;

    const stopCamera = () => {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
        video.hidden = true;
        scannerButton.disabled = false;
    };

    const scan = async () => {
        if (!scanning || !detector || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
            if (scanning) {
                window.setTimeout(scan, 150);
            }
            return;
        }

        try {
            const results = await detector.detect(video);
            const code = results[0]?.rawValue ?? "";
            if (/^[a-f0-9]{64}$/i.test(code)) {
                passCodeInput.value = code;
                methodInput.value = "qr";
                stopCamera();
                form.requestSubmit();
                return;
            }
        } catch {
            message.textContent = "The camera could not read a QR pass. You can enter its code manually instead.";
            stopCamera();
            return;
        }

        if (scanning) {
            window.setTimeout(scan, 180);
        }
    };

    scannerButton.addEventListener("click", async () => {
        if (!("BarcodeDetector" in window)) {
            message.textContent = "QR scanning is not supported in this browser. Enter the pass code manually instead.";
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            message.textContent = "Camera access is unavailable. Enter the pass code manually instead.";
            return;
        }

        scannerButton.disabled = true;
        try {
            detector = new BarcodeDetector({ formats: ["qr_code"] });
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: "environment" } }
            });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            message.textContent = "Point the camera at the attendee’s QR pass.";
            scanning = true;
            scan();
        } catch {
            stopCamera();
            message.textContent = "Camera permission or QR scanning is unavailable. Enter the pass code manually instead.";
        }
    });

    form.addEventListener("submit", () => {
        stopCamera();
    });
})();
