import React, { useState, useRef, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import "./MapCreator.css";

export default function Market1() {
  const [mapId, setMapId] = useState(null);
  const [stallCount, setStallCount] = useState(0);
  const [stalls, setStalls] = useState([]);
  const [mapImage, setMapImage] = useState(null); // Preview only
  const [mapFile, setMapFile] = useState(null);   // Actual file to upload
  const [isFinished, setIsFinished] = useState(false);
  const [stallClasses, setStallClasses] = useState([]);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [modalPos, setModalPos] = useState({ x: 0, y: 0 });

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  const navigate = useNavigate();
  const API_BASE = "http://localhost/revenue/backend/Market/MarketCreator";

  // Fetch stall classes from backend
  useEffect(() => {
    fetchStallClasses();
  }, []);

  const fetchStallClasses = async () => {
    try {
      const res = await fetch(`${API_BASE}/get_stall_classes.php`);
      const data = await res.json();
      if (data.status === "success") {
        setStallClasses(data.classes);
      } else {
        // Fallback default classes
        setStallClasses([
          { class_id: 1, class_name: "A", price: 1000, description: "Premium Location" },
          { class_id: 2, class_name: "B", price: 750, description: "Standard Location" },
          { class_id: 3, class_name: "C", price: 500, description: "Economy Location" }
        ]);
      }
    } catch (err) {
      console.error("Error fetching stall classes:", err);
      // Fallback if API fails
      setStallClasses([
        { class_id: 1, class_name: "A", price: 1000, description: "Premium Location" },
        { class_id: 2, class_name: "B", price: 750, description: "Standard Location" },
        { class_id: 3, class_name: "C", price: 500, description: "Economy Location" }
      ]);
    }
  };

  // Preview the map
  const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
      setMapFile(file);
      setMapImage(URL.createObjectURL(file));
    }
  };

  // Add a stall
  const addStall = () => {
    const newCount = stallCount + 1;
    const defaultClass = stallClasses.find(cls => cls.class_name === "C") || stallClasses[0];
    
    setStallCount(newCount);
    setStalls([
      ...stalls,
      { 
        name: `Stall ${newCount}`, 
        pos_x: 50, 
        pos_y: 50, 
        status: "available", 
        class_id: defaultClass.class_id,
        class_name: defaultClass.class_name,
        price: defaultClass.price,
        height: 0, 
        length: 0, 
        width: 0 
      }
    ]);
  };

  // Delete a stall
  const deleteStall = (index) => {
    if (window.confirm(`Delete ${stalls[index].name}?`)) {
      const updated = stalls.filter((_, i) => i !== index);
      setStalls(updated);
      setStallCount(updated.length);
    }
  };

  // Save map and stalls to backend
  const saveStalls = async () => {
    if (!mapFile) return alert("Select a map image first.");
    const mapName = document.querySelector('input[name="mapName"]').value || "Unnamed Map";

    const formData = new FormData();
    formData.append("mapName", mapName);
    formData.append("mapImage", mapFile);
    formData.append("stalls", JSON.stringify(stalls));

    try {
      const res = await fetch(`${API_BASE}/save_stalls.php`, {
        method: "POST",
        body: formData
      });
      const data = await res.json();
      if (data.status === "success") {
        alert("Map and stalls saved!");
        setMapId(data.map_id);
        navigate(`/Market/MarketOutput/view/${data.map_id}`);
      } else {
        alert("Save failed: " + (data.message || "Unknown"));
      }
    } catch (err) {
      alert("Upload error: " + err.message);
    }
  };

  // Drag stalls
  const handleDrag = (e, index) => {
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const x = e.clientX - containerRect.left - 31.5;
    const y = e.clientY - containerRect.top - 29;

    const updated = [...stalls];
    updated[index].pos_x = Math.max(0, Math.min(containerRect.width - 63, x));
    updated[index].pos_y = Math.max(0, Math.min(containerRect.height - 58, y));
    setStalls(updated);
  };

  const handleMouseDown = (e, index) => {
    e.preventDefault();
    const onMouseMove = (ev) => handleDrag(ev, index);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  // Open stall modal
  const openPriceModal = (index, e) => {
    e.preventDefault();
    setSelectedStallIndex(index);
    const viewportX = e.clientX;
    const viewportY = e.clientY;
    const modalWidth = 350;
    const modalHeight = 500;

    let x = viewportX;
    let y = viewportY;
    if (x + modalWidth > window.innerWidth) x = window.innerWidth - modalWidth - 10;
    if (y + modalHeight > window.innerHeight) y = window.innerHeight - modalHeight - 10;
    x = Math.max(10, x);
    y = Math.max(10, y);
    setModalPos({ x, y });
    setModalOpen(true);
  };

  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      setModalOpen(false);
    }
  };

  // Update stall class
  const updateStallClass = (class_id) => {
    const selectedClass = stallClasses.find(cls => cls.class_id == class_id);
    if (selectedClass) {
      const updated = [...stalls];
      updated[selectedStallIndex].class_id = selectedClass.class_id;
      updated[selectedStallIndex].class_name = selectedClass.class_name;
      // Don't auto-update price when class changes
      setStalls(updated);
    }
  };

  // Update price separately
  const updateStallPrice = (price) => {
    const updated = [...stalls];
    updated[selectedStallIndex].price = parseFloat(price) || 0;
    setStalls(updated);
  };

  return (
    <div className="market-container">
      <h1>{isFinished ? "Finished Market Map" : "Market Map Creator"}</h1>

      {!isFinished && (
        <div className="upload-form">
          <input type="text" name="mapName" placeholder="Map Name" required />
          <input type="file" name="mapImage" accept="image/*" onChange={handleFileSelect} required />
        </div>
      )}

      <div
        id="marketMap"
        ref={marketMapRef}
        className="market-map"
        style={{ backgroundImage: mapImage ? `url('${mapImage}')` : "none" }}
      >
        {stalls.map((stall, index) => (
          <div
            key={index}
            className={`stall ${stall.status}`}
            style={{ left: stall.pos_x, top: stall.pos_y }}
            onMouseDown={isFinished ? null : (e) => handleMouseDown(e, index)}
            onContextMenu={(e) => openPriceModal(index, e)}
          >
            <div className="stall-content">
              <div className="stall-name">{stall.name}</div>
              <div className="stall-class">Class: {stall.class_name}</div>
              <div className="stall-price">₱{stall.price}</div>
              <div className="stall-size">{stall.length}m × {stall.width}m × {stall.height}m</div>
            </div>

            {/* Delete button - always visible in edit mode */}
            {!isFinished && (
              <button
                className="delete-stall-btn"
                onClick={(e) => { e.stopPropagation(); deleteStall(index); }}
                title="Delete stall"
              >
                ×
              </button>
            )}
          </div>
        ))}
      </div>

      {modalOpen && (
        <div className="modal-backdrop" onClick={handleBackdropClick}>
          <div
            ref={modalRef}
            className="price-modal"
            style={{ left: `${modalPos.x}px`, top: `${modalPos.y}px`, position: 'fixed' }}
            onClick={(e) => e.stopPropagation()}
          >
            <h4>Set Stall Details - {stalls[selectedStallIndex]?.name}</h4>

            <label>Stall Class</label>
            <select
              value={stalls[selectedStallIndex]?.class_id || ""}
              onChange={(e) => updateStallClass(e.target.value)}
            >
              {stallClasses.map((cls) => (
                <option key={cls.class_id} value={cls.class_id}>
                  Class {cls.class_name} - ₱{cls.price} ({cls.description})
                </option>
              ))}
            </select>

            <div className="current-class-info">
              <strong>Selected: Class {stalls[selectedStallIndex]?.class_name}</strong>
              <br />
              <span>Suggested Price: ₱{stallClasses.find(cls => cls.class_id == stalls[selectedStallIndex]?.class_id)?.price || 0}</span>
            </div>

            <label>Custom Price (₱)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.price || 0}
              onChange={(e) => updateStallPrice(e.target.value)}
              step="0.01"
              min="0"
            />

            <label>Height (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.height || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].height = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Length (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.length || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].length = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Width (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.width || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].width = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <div className="modal-buttons">
              <button onClick={() => setModalOpen(false)}>Save</button>
              <button onClick={() => setModalOpen(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      {!isFinished && (
        <div className="controls">
          <button onClick={addStall} disabled={!mapImage}>Add Stall</button>
          <button onClick={saveStalls} disabled={!mapFile}>Save Stalls</button>
          <button onClick={() => navigate("/Market/ViewAllMaps")}>View All Maps</button>
        </div>
      )}
    </div>
  );
}