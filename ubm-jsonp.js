(() => {
    try {
        let iter_index = 0;
        let payload = "";

        const ubm_tmp_banners = Array.from(document.querySelectorAll("a.ubm-banner"));

        iter_index = 0;
        for (const banner of ubm_tmp_banners) {
            let id = String(banner.dataset.id);
            banner.id = `ubm_${iter_index}`;
            payload += `${id}:`;
            iter_index++;
        }
        if (iter_index > 0) {
            fetch("https://www.adbirt.com/ubm_getbanner", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    ubm_banners: payload,
                    ubm_anticache: (Math.random()).toString(),
                    action: "ubm_getbanner"
                })
            }).then(async (res) => {
                if (res.ok) {
                    const payload = await res.json();
                    const banners = JSON.parse(payload.html);
                    iter_index = 0;
                    for (const banner of banners) {
                        // console.log(banner.trim());
                        if (banner && (typeof banner == 'string') && banner.length && (banner.match("ubm_banner") != null)) {
                            Array.from(document.querySelectorAll("#ubm_" + iter_index)).forEach(item => {
                                const tmpDiv = document.createElement("div");
                                tmpDiv.innerHTML = banner.trim();
                                item.replaceWith(tmpDiv.firstChild);
                                tmpDiv.remove();
                            });
                        }
                        iter_index++;
                    }
                }
            }).catch((err) => {
                console.error('Adbirt publisher script error: ', err);
            });
        }
    } catch (error) {
        console.error(error);
    }
})();